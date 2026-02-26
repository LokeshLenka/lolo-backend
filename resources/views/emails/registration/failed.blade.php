<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @media only screen and (max-width: 600px) {
            .main-container {
                padding: 20px 12px !important;
            }

            .glass-card {
                padding: 30px 20px !important;
            }

            .data-grid {
                padding: 16px !important;
            }

            .header-text {
                font-size: 22px !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #fef2f2; color: #0f172a; -webkit-font-smoothing: antialiased;">

    <!-- Light red pastel gradient background -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background: #fef2f2; background-image: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);">
        <tr>
            <td align="center" class="main-container" style="padding: 50px 20px;">

                <div style="max-width: 500px; margin: 0 auto; text-align: left;">

                    <div style="text-align: center; margin-bottom: 24px;">
                        <p
                            style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #dc2626;">
                            Registration Failed</p>
                    </div>

                    <!-- Light Glassmorphic Card -->
                    <div class="glass-card"
                        style="background: rgba(255, 255, 255, 0.85); border: 1px solid rgba(255, 255, 255, 1); border-radius: 20px; padding: 40px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);">

                        <h1 class="header-text"
                            style="margin: 0 0 12px 0; font-size: 24px; font-weight: 800; color: #0f172a;">Hello,
                            {{ $participantData['name'] }}</h1>
                        <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.6; color: #475569;">
                            We could not verify your payment for <strong
                                style="color: #0f172a;">{{ $participantData['event_name'] }}</strong>. As a result, your
                            registration has been cancelled.
                        </p>

                        <!-- Data Grid -->
                        <div class="data-grid"
                            style="background: rgba(248, 250, 252, 0.8); border-radius: 14px; padding: 24px; border: 1px solid rgba(226, 232, 240, 0.8);">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="font-size: 14px;">
                                <tr>
                                    <td style="padding-bottom: 14px; color: #64748b; font-weight: 500;">Student Reg No.
                                    </td>
                                    <td
                                        style="padding-bottom: 14px; text-align: right; font-weight: 700; color: #0f172a;">
                                        {{ $participantData['reg_number'] }}</td>
                                </tr>
                                <tr>
                                    <td
                                        style="padding-bottom: 14px; color: #64748b; font-weight: 500; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        UTR Provided</td>
                                    <td
                                        style="padding-bottom: 14px; text-align: right; font-family: monospace; font-size: 14px; color: #334155; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        {{ $participantData['utr_number'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td
                                        style="color: #64748b; font-weight: 500; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        Status</td>
                                    <td style="text-align: right; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        <span
                                            style="display: inline-block; background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700;">Rejected</span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Optimized Footer -->
                        <div
                            style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="margin: 0 0 12px 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                If you believe this is a mistake, please email a screenshot of your payment to
                                <a href="mailto:bandloloplays0707@gmail.com"
                                    style="color: #2563eb; text-decoration: none; font-weight: 600;">bandloloplays0707@gmail.com</a>.
                            </p>
                            <p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                Need immediate help?
                                <a href="https://www.srkrlolo.in/events/9abdbf2e-37d1-4adb-ad26-779bc6a3951c"
                                    style="color: #dc2626; text-decoration: none; font-weight: 600;">
                                    Contact our student coordinators &rarr;
                                </a>
                            </p>
                        </div>

                    </div>
                </div>

            </td>
        </tr>
    </table>
</body>

</html>
