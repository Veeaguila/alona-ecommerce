<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title>{{ $subject ?? 'Your Alona Verification Code' }}</title>
    <style type="text/css">
        /* Client-specific styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #F4F7F6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        /* Mobile styles */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: auto !important; }
            .content-padding { padding: 24px 20px !important; }
            .otp-code { font-size: 32px !important; letter-spacing: 8px !important; }
            .header-padding { padding: 24px 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #F4F7F6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1E293B;">
    <!-- Hidden Preheader for Gmail inbox preview -->
    <div style="display: none; font-size: 1px; color: #F4F7F6; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all;">
        Your Alona verification code is {{ $otpCode }}. Valid for 5 minutes. Never share this code with anyone.
    </div>

    <!-- Main Outer Wrapper -->
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F4F7F6; padding: 30px 15px;">
        <tr>
            <td align="center">
                <!-- Email Main Card (Max Width 560px) -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width: 560px; background-color: #FFFFFF; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(8, 127, 140, 0.08); border: 1px border #E5E7EB;">
                    
                    <!-- Top Teal Brand Header Banner -->
                    <tr>
                        <td class="header-padding" align="center" style="background: linear-gradient(135deg, #03535D 0%, #087F8C 60%, #16A6A0 100%); padding: 32px 30px 28px; text-align: center;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td align="center" style="padding-bottom: 8px;">
                                        <!-- Alona Bag Logo Icon -->
                                        <div style="display: inline-block; width: 44px; height: 44px; background-color: rgba(255, 255, 255, 0.18); border-radius: 12px; text-align: center; line-height: 44px; box-shadow: inset 0 0 10px rgba(255, 255, 255, 0.2);">
                                            <span style="font-size: 20px; font-weight: 800; color: #FFFFFF; vertical-align: middle;">A</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <h1 style="margin: 0; color: #FFFFFF; font-size: 22px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase;">
                                            ALONA
                                        </h1>
                                        <p style="margin: 2px 0 0; color: #D1FAF4; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;">
                                            E-Commerce &amp; Logistics Network
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td class="content-padding" style="padding: 36px 36px 28px;">
                            <!-- Heading -->
                            <h2 style="margin: 0 0 12px; color: #0F172A; font-size: 20px; font-weight: 700; text-align: center;">
                                {{ $title ?? 'Verify Your Email Address' }}
                            </h2>
                            
                            <p style="margin: 0 0 20px; color: #475569; font-size: 14px; line-height: 1.6; text-align: center;">
                                Hello <strong style="color: #0F172A;">{{ $userName ?? 'there' }}</strong>,<br />
                                {{ $introText ?? 'Thank you for joining Alona. To complete your account verification, please enter the 6-digit One-Time Password (OTP) below:' }}
                            </p>

                            <!-- OTP Code Box (Highlight Card) -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 24px 0 20px;">
                                <tr>
                                    <td align="center" style="background-color: #F0FDFA; border: 2px dashed #087F8C; border-radius: 16px; padding: 22px 16px; text-align: center;">
                                        <span style="display: block; color: #087F8C; font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px;">
                                            ONE-TIME VERIFICATION CODE
                                        </span>
                                        
                                        <!-- OTP Digits -->
                                        <div class="otp-code" style="font-family: 'SF Mono', Monaco, 'Courier New', Courier, monospace; font-size: 38px; font-weight: 800; color: #087F8C; letter-spacing: 10px; margin-left: 10px; text-align: center;">
                                            {{ $otpCode }}
                                        </div>

                                        <!-- Expiry Badge -->
                                        <div style="margin-top: 10px; display: inline-block; background-color: rgba(8, 127, 140, 0.12); border-radius: 20px; padding: 4px 14px;">
                                            <span style="color: #045C68; font-size: 11px; font-weight: 600;">
                                                Expires in <strong>5 minutes</strong>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security Tip Notice -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px; margin: 20px 0;">
                                <tr>
                                    <td style="padding: 12px 16px;">
                                        <p style="margin: 0; color: #92400E; font-size: 12px; line-height: 1.5;">
                                            <strong>Security Warning:</strong> Never share this verification code with anyone, including individuals claiming to be Alona support staff.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 20px 0 0; color: #64748B; font-size: 12px; line-height: 1.5; text-align: center;">
                                If you did not request this verification code or create an Alona account, you can safely ignore this email. No action is required.
                            </p>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 36px;">
                            <div style="height: 1px; background-color: #F1F5F9; width: 100%;"></div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 36px 32px; text-align: center; background-color: #FAFBFB;">
                            <p style="margin: 0 0 8px; color: #64748B; font-size: 11px; font-weight: 600;">
                                Alona · Philippines E-Commerce &amp; 3PL Logistics Network
                            </p>
                            <p style="margin: 0 0 12px; color: #94A3B8; font-size: 11px; line-height: 1.5;">
                                This is an automated security message. Please do not reply directly to this email.<br />
                                For assistance, visit our <a href="{{ config('app.url') }}/policies" style="color: #087F8C; text-decoration: underline; font-weight: 600;">Help &amp; Policies Center</a>.
                            </p>
                            <p style="margin: 0; color: #CBD5E1; font-size: 10px;">
                                &copy; {{ date('Y') }} Alona Technologies Inc. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Bottom Micro Links -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; margin-top: 15px;">
                    <tr>
                        <td align="center" style="font-size: 11px; color: #94A3B8;">
                            <a href="{{ config('app.url') }}/policies" style="color: #64748B; text-decoration: none; margin: 0 8px;">Privacy Policy</a> &bull;
                            <a href="{{ config('app.url') }}/policies" style="color: #64748B; text-decoration: none; margin: 0 8px;">Terms of Service</a> &bull;
                            <a href="{{ config('app.url') }}" style="color: #64748B; text-decoration: none; margin: 0 8px;">Shop Alona</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

