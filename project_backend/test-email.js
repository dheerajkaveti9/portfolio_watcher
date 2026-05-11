require("dotenv").config();
const nodemailer = require("nodemailer");

async function sendTest() {
  const transporter = nodemailer.createTransport({
    service: "gmail",
    auth: {
      user: process.env.EMAIL_USER,
      pass: process.env.EMAIL_PASSWORD
    }
  });

  try {
    await transporter.sendMail({
      from: process.env.EMAIL_USER,
      to: process.env.EMAIL_USER,
      subject: "Test Email",
      text: "Email works!"
    });

    console.log("Email sent!");
  } catch (err) {
    console.error("Email error:", err);
  }
}

sendTest();
