<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - Homisha</title>
    <style>
        body {
            background-color: #1F4C6B;
            font-family: Arial, sans-serif;
            color: #FFFFFF;
            text-align: center;
            margin: 0;
            padding: 0;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .logo {
            width: 100px;
            height: 100px;
            background-color: #FFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo img {
            max-width: 90%;
            max-height: 90%;
        }

        .title {
            font-size: 36px;
            margin-top: 20px;
        }

        .slogan {
            font-size: 18px;
            margin-top: 10px;
        }

        .coming-soon {
            font-size: 24px;
            margin-top: 30px;
        }

        .contact {
            font-size: 16px;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="logo">
            <!-- Placeholder for logo -->
            <img src="{{ asset('assets/logo.png') }}" alt="Logo">
        </div>
        <div class="title">Homisha</div>
        <div class="slogan">Find Your Home, Not Just a House</div>
        <div class="coming-soon">Coming Soon</div>
        <div class="contact">
            <p>Phone: +254748355080</p>
            <p>Email: info@banit.co.ke</p>
        </div>
    </div>

</body>

</html>
