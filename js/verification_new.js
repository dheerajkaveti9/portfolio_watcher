// Email Verification - Node.js Version (FINAL)
const API_URL = "http://localhost:3000";

document.addEventListener("DOMContentLoaded", () => {
    const sendCodeBtn = document.getElementById("sendCode");
    const resendCodeBtn = document.getElementById("resendCode");
    const verifyForm = document.getElementById("verifyForm");
    const emailInput = document.getElementById("email");
    const codeInput = document.getElementById("code");

    // Get email from signup redirect
    const urlParams = new URLSearchParams(window.location.search);
    const emailParam = urlParams.get("email");
    if (emailParam) {
        emailInput.value = emailParam;
        emailInput.readOnly = true;

        // Auto-send code
        setTimeout(() => sendCodeBtn.click(), 400);
    }

    // Send code button
    sendCodeBtn.addEventListener("click", async () => {
        await sendVerificationCode(false);
    });

    // Resend code button
    resendCodeBtn.addEventListener("click", async (e) => {
        e.preventDefault();
        await sendVerificationCode(true);
    });

    // Submit verification
    verifyForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const email = emailInput.value.trim();
        const code = codeInput.value.trim();

        if (!email || !code) {
            return showMessage("error", "Enter both email and code");
        }
        if (code.length !== 6) {
            return showMessage("error", "Code must be 6 digits");
        }

        const btn = verifyForm.querySelector("button[type=submit]");
        btn.disabled = true;
        btn.textContent = "Verifying...";

        try {
            const res = await fetch(`${API_URL}/api/auth/verify-email`, {
                method: "POST",
                credentials: "include",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email, code })
            });

            const result = await res.json();
            console.log(result);

            if (result.success) {
                showMessage("success", "Email verified! Redirecting...");

                setTimeout(() => {
                    window.location.href = "login.html";
                }, 1500);
            } else {
                showMessage("error", result.message);
                btn.disabled = false;
                btn.textContent = "Verify Account";
            }

        } catch (err) {
            console.error(err);
            showMessage("error", "Server error. Try again.");
            btn.disabled = false;
            btn.textContent = "Verify Account";
        }
    });

    // SEND CODE FUNCTION (Node.js)
    async function sendVerificationCode(isResend) {
        const email = emailInput.value.trim();
        if (!email) {
            return showMessage("error", "Enter your email");
        }

        const btn = isResend ? resendCodeBtn : sendCodeBtn;
        const original = btn.textContent;

        btn.disabled = true;
        btn.textContent = "Sending...";

        try {
            const res = await fetch(`${API_URL}/api/auth/send-code`, {
                method: "POST",
                credentials: "include",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email })
            });

            const result = await res.json();
            console.log(result);

            if (result.success) {
                showMessage("success", "Verification code sent!");
                codeInput.focus();

                // Optional debug
                if (result.code) {
                    console.log("DEBUG CODE:", result.code);
                }

                // Resend timer
                if (isResend) {
                    let time = 30;
                    const interval = setInterval(() => {
                        btn.textContent = `Resend (${time}s)`;
                        if (time-- <= 0) {
                            clearInterval(interval);
                            btn.disabled = false;
                            btn.textContent = "Resend";
                        }
                    }, 1000);
                } else {
                    btn.disabled = false;
                    btn.textContent = original;
                }

            } else {
                showMessage("error", result.message);
                btn.disabled = false;
                btn.textContent = original;
            }

        } catch (err) {
            console.error(err);
            showMessage("error", "Network error");
            btn.disabled = false;
            btn.textContent = original;
        }
    }
});

function showMessage(type, message) {
    const old = document.querySelector(".alert-message");
    if (old) old.remove();

    const div = document.createElement("div");
    div.className = "alert-message";
    div.style = `
        background: ${type === "success" ? "#10b981" : "#ef4444"};
        color: white; padding: 12px; 
        margin-bottom: 12px; border-radius: 8px;
    `;
    div.textContent = message;
    document.getElementById("verifyForm").prepend(div);

    setTimeout(() => div.remove(), 5000);
}
