<?php
// PHPMailer Download Script
// This will download and extract PHPMailer automatically

echo "Downloading PHPMailer...\n";

$phpmailerUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip';
$zipFile = 'phpmailer.zip';
$extractPath = __DIR__ . '/vendor/phpmailer';

// Create vendor directory if it doesn't exist
if (!is_dir(__DIR__ . '/vendor')) {
    mkdir(__DIR__ . '/vendor', 0777, true);
}

// Download the ZIP file
echo "Downloading from GitHub...\n";
$zipContent = file_get_contents($phpmailerUrl);

if ($zipContent === false) {
    die("Failed to download PHPMailer. Please check your internet connection.\n");
}

file_put_contents($zipFile, $zipContent);
echo "Download complete!\n";

// Extract ZIP file
echo "Extracting files...\n";
$zip = new ZipArchive();
if ($zip->open($zipFile) === TRUE) {
    // Extract to vendor/phpmailer
    $zip->extractTo(__DIR__ . '/vendor/');
    $zip->close();
    
    // Rename the extracted folder
    $extractedFolder = __DIR__ . '/vendor/PHPMailer-6.9.1';
    if (is_dir($extractedFolder)) {
        if (is_dir($extractPath)) {
            rmdir($extractPath);
        }
        rename($extractedFolder, $extractPath);
    }
    
    // Move src folder to correct location
    $srcPath = $extractPath . '/src';
    if (is_dir($srcPath)) {
        echo "PHPMailer installed successfully!\n";
        echo "Location: vendor/phpmailer/src/\n";
    } else {
        echo "Warning: src folder not found in expected location.\n";
    }
    
    // Clean up
    unlink($zipFile);
    echo "Installation complete!\n";
} else {
    die("Failed to extract ZIP file.\n");
}

?>







