// Signup Form Handler - Node.js Version
// Location: C:\xampp\htdocs\portfolio_watcher\js\signup.js

const API_URL = "http://localhost:3000";

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('signupForm');

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Collect form data
            const fullname = document.getElementById("fullname").value.trim();
            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value.trim();
            const confirmPassword = document.getElementById("confirm-password").value.trim();

            // Validate
            if (password !== confirmPassword) {
                showMessage("error", "Passwords do not match");
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = "Creating Account...";

            clearErrors();

            try {
                const res = await fetch(`${API_URL}/api/auth/register`, {
                    method: "POST",
                    credentials: "include",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        fullname,
                        email,
                        password
                    })
                });

                const result = await res.json();
                console.log("Signup response:", result);

                if (result.success) {
                    showMessage("success", result.message + " Redirecting...");

                    // Redirect to email verification
                    setTimeout(() => {
                        window.location.href =
                            `verification.html?email=${encodeURIComponent(email)}`;
                    }, 1500);

                } else {
                    showMessage("error", result.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }

            } catch (err) {
                console.error("Signup Error:", err);
                showMessage("error", "Server error, please try again.");
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }
});

// ------------------------
// Message Handlers
// ------------------------

function showMessage(type, msg) {
    clearErrors();

    const div = document.createElement("div");
    div.className = "alert-message";
    div.style.cssText = `
        padding: 12px;
        margin-bottom: 15px;
        border-radius: 8px;
        font-size: 14px;
        color: white;
        background: ${type === "success" ? "#10b981" : "#ef4444"};
    `;
    div.textContent = msg;

    const form = document.getElementById("signupForm");
    form.prepend(div);

    setTimeout(() => div.remove(), 5000);
}

function clearErrors() {
    const msg = document.querySelector(".alert-message");
    if (msg) msg.remove();
}
