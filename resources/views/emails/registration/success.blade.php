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

            .qr-img {
                width: 160px !important;
                height: 160px !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f0fdf4; color: #0f172a; -webkit-font-smoothing: antialiased;">

    <!-- Light green pastel gradient background -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background: #f0fdf4; background-image: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);">
        <tr>
            <td align="center" class="main-container" style="padding: 50px 20px;">

                <div style="max-width: 500px; margin: 0 auto; text-align: left;">

                    <div style="text-align: center; margin-bottom: 24px;">
                        <p
                            style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #059669;">
                            Registration Confirmed</p>
                    </div>

                    <!-- Light Glassmorphic Card -->
                    <div class="glass-card"
                        style="background: rgba(255, 255, 255, 0.85); border: 1px solid rgba(255, 255, 255, 1); border-radius: 20px; padding: 40px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);">

                        <h1 class="header-text"
                            style="margin: 0 0 12px 0; font-size: 24px; font-weight: 800; color: #0f172a;">You're in,
                            {{ $participantData['name'] }}! </h1>
                        <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.6; color: #475569;">
                            Your spot for <strong style="color: #0f172a;">{{ $participantData['event_name'] }}</strong>
                            is secured. Present this QR code at the entrance.
                        </p>

                        <!-- QR Code Section -->
                        <div style="text-align: center; margin-bottom: 24px;">
                            <div class="qr-wrapper"
                                style="display: inline-block; background: #ffffff; padding: 16px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                                <img class="qr-img"
                                    src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=H&data={{ $participantData['ticket_code'] }}"
                                    alt="Ticket QR" width="180" height="180"
                                    style="display: block; border-radius: 4px;">
                            </div>
                        </div>


                        <!-- Data Grid -->
                        <div class="data-grid"
                            style="background: rgba(248, 250, 252, 0.8); border-radius: 14px; padding: 24px; border: 1px solid rgba(226, 232, 240, 0.8);">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="font-size: 14px;">
                                <tr>
                                    <td style="padding-bottom: 14px; color: #64748b; font-weight: 500;">Ticket Code</td>
                                    <td
                                        style="padding-bottom: 14px; text-align: right; font-family: monospace; font-size: 14px; font-weight: 700; color: #059669;">
                                        {{ $participantData['ticket_code'] }}</td>
                                </tr>
                                <tr>
                                    <td
                                        style="padding-bottom: 14px; color: #64748b; font-weight: 500; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        Student Reg No.</td>
                                    <td
                                        style="padding-bottom: 14px; text-align: right; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        {{ $participantData['reg_number'] }}</td>
                                </tr>
                                <tr>
                                    <td
                                        style="color: #64748b; font-weight: 500; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        Status</td>
                                    <td style="text-align: right; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                                        <span
                                            style="display: inline-block; background: #d1fae5; color: #059669; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700;">Confirmed</span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                    </div>
                </div>

            </td>
        </tr>
    </table>
</body>

</html>
