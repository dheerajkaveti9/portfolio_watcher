// routes/notion.js
const express = require('express');
const fetch = require('node-fetch'); // or global fetch if Node 18+
const router = express.Router();

const NOTION_TOKEN = process.env.NOTION_TOKEN;
const NOTION_VERSION = '2022-06-28'; // stable Notion version header

if (!NOTION_TOKEN) {
  console.warn('WARNING: NOTION_TOKEN is not set. /api/notion-content will fail without it.');
}

// Extract UUID-like id from a Notion shared URL
function extractNotionId(url) {
  // Many Notion urls end with -<uuid> or have the uuid as the last path piece or query param
  // Match 32 hex optionally with hyphens in the usual Notion uuid format
  const m = url.match(/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i)
         || url.match(/([0-9a-f]{32})/i);
  if (!m) return null;
  return m[1];
}

// Generic request helper to Notion API
async function notionFetch(path, params = {}) {
  const url = `https://api.notion.com/v1${path}`;
  const res = await fetch(url, {
    method: params.method || 'GET',
    headers: {
      'Authorization': `Bearer ${NOTION_TOKEN}`,
      'Notion-Version': NOTION_VERSION,
      'Content-Type': 'application/json'
    },
    body: params.body ? JSON.stringify(params.body) : undefined
  });

  if (!res.ok) {
    const text = await res.text();
    const err = new Error(`Notion API ${res.status} ${res.statusText}: ${text}`);
    err.status = res.status;
    throw err;
  }

  return res.json();
}

// Flatten array of rich_text objects to plain string
function richTextToPlain(richTextArray) {
  if (!Array.isArray(richTextArray)) return '';
  return richTextArray.map(rt => rt.plain_text || '').join('');
}

// Convert a Notion block object to plain text (including nested bullets)
function blockToText(block) {
  if (!block) return '';
  const t = block.type;
  switch (t) {
    case 'paragraph':
      return richTextToPlain(block.paragraph.rich_text);
    case 'heading_1':
    case 'heading_2':
    case 'heading_3':
      return richTextToPlain(block[t].rich_text);
    case 'bulleted_list_item':
    case 'numbered_list_item':
      return richTextToPlain(block[t].rich_text);
    case 'quote':
      return richTextToPlain(block.quote.rich_text);
    case 'code':
      return (block.code.rich_text || []).map(r=>r.plain_text).join('') + '\n';
    case 'callout':
      return richTextToPlain(block.callout.rich_text);
    case 'to_do':
      return (block.to_do.checked ? '[x] ' : '[ ] ') + richTextToPlain(block.to_do.rich_text);
    case 'toggle':
      return richTextToPlain(block.toggle.rich_text);
    case 'table':
    case 'table_row':
      // skip complex rendering; handle table rows as concatenated cells if needed
      return '';
    case 'image':
    case 'video':
    case 'embed':
      return `[${t.toUpperCase()}] ${block[t].caption ? richTextToPlain(block[t].caption) : ''}`;
    default:
      // fallback for unknown/new block types
      // try to access first rich_text if present
      for (const key of Object.keys(block)) {
        if (block[key] && block[key].rich_text) {
          return richTextToPlain(block[key].rich_text);
        }
      }
      return '';
  }
}

// Recursively fetch children blocks (handles pagination)
async function fetchBlockChildren(block_id) {
  let all = [];
  let start_cursor = undefined;

  while (true) {
    const path = `/blocks/${block_id}/children${start_cursor ? `?start_cursor=${start_cursor}` : ''}`;
    const res = await notionFetch(`/blocks/${block_id}/children${start_cursor ? `?start_cursor=${start_cursor}` : ''}`);
    all = all.concat(res.results || []);
    if (!res.has_more) break;
    start_cursor = res.next_cursor;
  }

  return all;
}

// Recursively walk blocks and collect text (handles nested children)
async function collectTextFromBlock(block) {
  let out = [];
  const text = blockToText(block);
  if (text && text.trim()) out.push(text.trim());

  // If block has children, fetch them and recurse
  if (block.has_children) {
    try {
      const children = await fetchBlockChildren(block.id);
      for (const child of children) {
        const childText = await collectTextFromBlock(child);
        if (childText) out.push(childText);
      }
    } catch (e) {
      // Non-fatal; continue, but include an indicator
      out.push(`[Error fetching children for block ${block.id}]`);
    }
  }

  return out.join('\n');
}

router.get('/notion-content', async (req, res) => {
  try {
    const url = req.query.url;
    if (!url) return res.status(400).json({ success: false, error: 'Missing url param' });

    if (!NOTION_TOKEN) {
      return res.status(500).json({ success: false, error: 'Server Notion token not configured' });
    }

    const pageId = extractNotionId(url);
    if (!pageId) {
      return res.status(400).json({ success: false, error: 'Could not extract Notion page id from URL' });
    }

    // Notion pages are "pages". We can retrieve the page's top-level blocks by treating pageId as a block id
    // Fetch top-level blocks
    const blocksRes = await notionFetch(`/blocks/${pageId}/children`);
    const blocks = blocksRes.results || [];

    // Collect text from each top-level block recursively
    const pieces = [];
    for (const block of blocks) {
      const piece = await collectTextFromBlock(block);
      if (piece && piece.trim()) pieces.push(piece.trim());
    }

    // Return combined text
    const text = pieces.join('\n\n');
    return res.json({ success: true, text });

  } catch (err) {
    console.error('Notion fetch error:', err && err.message ? err.message : err);
    const message = err.message || 'Unknown error';
    return res.status(500).json({ success: false, error: `Notion fetch failed: ${message}` });
  }
});

module.exports = router;
