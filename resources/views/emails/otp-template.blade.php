<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homisha Authentication OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .email-container {
            background-color: white;
            width: 600px;
            margin: 20px auto;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        header {
            background-color: #1F4C6B;
            color: white;
            padding: 10px;
            text-align: center;
        }

        .otp-section {
            padding: 20px;
            text-align: center;
        }

        .otp-section h2 {
            color: #1F4C6B;
        }

        .otp {
            font-weight: bold;
            color: #8BC83F;
        }

        .info {
            font-size: 0.9em;
            color: #666;
        }

        footer {
            background-color: #1F4C6B;
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 0.8em;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <header>
            <h1>Homisha</h1>
        </header>
        <section class="otp-section">
            <h2>One-Time Password (OTP)</h2>
            <br>
            <p>Your OTP is:
            <h3><span class="otp">{{ $otp }}</span></h3>
            </p>
            <br>
            <p class="info">Please use this code to proceed with your transaction. Do not share this OTP with anyone.
                If you didn't request for OTP from <a href="https://banit.co.ke/">Homisha</a> ignore this message</p>
        </section>
        <footer>
            <p>Contact us: info@banit.co.ke | &copy; <span id="current-year"></span> Homisha</p>
        </footer>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        const currentYear = new Date().getFullYear();
        document.getElementById('current-year').textContent = currentYear;
    });
</script>

</html>
