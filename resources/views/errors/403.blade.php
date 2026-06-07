<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Restricted | DTS</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.18), transparent 35%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.18), transparent 35%),
                #f4f8ff;
            color: #0f172a;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }

        .card {
            width: 100%;
            max-width: 920px;
            overflow: hidden;
            border-radius: 34px;
            background: #ffffff;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.16);
            border: 1px solid rgba(37, 99, 235, 0.12);
            display: grid;
            grid-template-columns: 1fr 1.2fr;
        }

        .left {
            position: relative;
            background: linear-gradient(135deg, #1d4ed8, #2563eb, #0284c7);
            color: white;
            padding: 46px 38px;
            overflow: hidden;
        }

        .left::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            top: -90px;
            right: -100px;
        }

        .left::after {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.10);
            bottom: -90px;
            left: -80px;
        }

        .logo-box {
            position: relative;
            z-index: 1;
            width: 88px;
            height: 88px;
            border-radius: 26px;
            background: #ffffff;
            padding: 12px;
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .label {
            position: relative;
            z-index: 1;
            margin-top: 36px;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #bfdbfe;
        }

        .code {
            position: relative;
            z-index: 1;
            margin: 14px 0 0;
            font-size: 86px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.08em;
        }

        .left-text {
            position: relative;
            z-index: 1;
            margin-top: 14px;
            font-size: 15px;
            line-height: 1.7;
            color: #dbeafe;
            max-width: 310px;
        }

        .right {
            padding: 54px 46px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        h1 {
            margin: 24px 0 0;
            font-size: 38px;
            line-height: 1.1;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: #0f172a;
        }

        .message {
            margin-top: 18px;
            font-size: 16px;
            line-height: 1.8;
            font-weight: 600;
            color: #475569;
        }

        .message strong {
            color: #1d4ed8;
        }

        .notice {
            margin-top: 24px;
            border-radius: 22px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 18px 20px;
            color: #334155;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 600;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            border-radius: 16px;
            padding: 0 22px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 900;
            transition: 0.2s ease;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.28);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-light {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
        }

        .btn-light:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .footer-text {
            margin-top: 26px;
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
        }

        @media (max-width: 820px) {
            .card {
                grid-template-columns: 1fr;
            }

            .left {
                padding: 34px 28px;
            }

            .right {
                padding: 34px 28px;
            }

            .code {
                font-size: 64px;
            }

            h1 {
                font-size: 30px;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <section class="card">
            <div class="left">
                <div class="logo-box">
                    <img src="/images/logo_dts-nobg.png" alt="DTS Logo">
                </div>

                <div class="label">
                    DTS Security
                </div>

                <div class="code">
                    403
                </div>

                <p class="left-text">
                    This page is protected and only authorized accounts can access this area.
                </p>
            </div>

            <div class="right">
                <div class="badge">
                    <span>🔒</span>
                    <span>Access Restricted</span>
                </div>

                <h1>
                    You are not allowed to open this page.
                </h1>

                <p class="message">
                    Your account does not have permission to access this module.
                    Please return to the DTS dashboard or contact the system administrator
                    if you think this is a mistake.
                </p>

               

                
                
            </div>
        </section>
    </main>
</body>
</html>