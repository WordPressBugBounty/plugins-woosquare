<?php
/**
 * Success email template for Square connection alerts.
 *
 * @package Woosquare_Plus
 * @subpackage Square_Connection
 */

$body = '<div class="container" style="max-width: 700px;margin: 0 auto;width: 100%;">
    <table width="100%" cellspacing="0" style="width: 100%; background:#FAFBFB; font-family: "Sora", sans-serif; border-spacing: 0;">
        <tr style="background: #47B388;">
            <td style="padding: 26px 36px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img style="width: 20px; height: 20px; float: left; margin: 5px 10px 0 0;" src="' . $images_path . 'check-white.png"/>
                    <h1 style="margin:0;font-weight:600;font-size:16px;color:#fff;line-height: normal;">Square Connection Alert <span style="display: block; font-size: 12px; font-weight: 400;">Connection Successful</span></h1>
                </div>
            </td>
            <td style="padding: 26px 36px; text-align: right;">
                <img src="' . $images_path . 'wc-emal-logo.png"/>
            </td>
        </tr>
        <tr style="background: #F4FCF7;">
            <td colspan="2" style="padding: 22px;">
                <h2 style="font-size: 16px; color: #1D1D1B; font-weight: 600; margin: 0 0 5px 0;">Your Square API connection has been established</h2>
                <p style="font-size: 14px; color: #1D1D1B; font-weight: 400;  margin: 0;">The connection has been verified and is now active.</p>
            </td>
        </tr>
        <tr style="background: #fff; padding: 30px 22px;">
            <td colspan="2">
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 23px"><h3 style="margin: 0;">Connection Details</h3></td>
                        <td style="padding: 23px"></td>
                    </tr>
                     <tr>
                        <td style="width: 50%;padding: 20px 23px; border-bottom: 1px solid #DADEE9;"><p style="margin: 0;">Square Mode</p></td>
                        <td style="width: 50%;padding: 20px 23px; border-bottom: 1px solid #DADEE9; text-align: right;"><a style="color: #727C8C;border: 1px solid #727C8C;font-weight: 700;font-size: 12px;padding: 8px 20px;text-decoration: none;border-radius: 100px;" href="#">Sandbox</a></td>
                    </tr> 
                    <tr>
                        <td style="width: 50%;padding: 20px 23px; border-bottom: 1px solid #DADEE9;"><p style="margin: 0;">Date & Time</p></td>
                        <td style="width: 50%;padding: 20px 23px; border-bottom: 1px solid #DADEE9; text-align: right;"><p style="margin: 0; font-weight: 700; color: #727C8C;">6:22pm - 8/27/2025</p></td>
                    </tr>
                    <tr>
                        <td style="width: 50%;padding: 20px 23px; border-bottom: 1px solid #DADEE9;"><p style="margin: 0;">Connection Status</p></td>
                        <td style="width: 50%;padding: 20px 23px; border-bottom: 1px solid #DADEE9; text-align: right;">
                            <p style="margin:0;font-weight:700;color:#47b388;float: right;">
                                <img src="' . $images_path . 'checked.png" style="width:16px;height:16px;margin: 1px 6px 0 0;float: left;"/>
                                Connected
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;padding: 20px 23px;"></td>
                        <td style="width: 50%;padding: 20px 23px;"></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr style="background: #F1F5F9; padding: 30px 22px;">
            <td style="width: 60%;padding: 26px 37px;">
                <h2 style="color: #000; font-weight: 700; font-size: 16px;">Supercharge Your WooCommerce Store with WC Shop Sync Pro ⚡</h2>
                <ul style="margin: 0 0 10px; padding: 0; list-style: none; font-size: 14px;">
                    <li style="padding: 5px 0;display: flex;align-items: center;gap: 5px;"><img style="width: 15px;height: 15px;margin: 5px 5px 0 0;" src="' . $images_path . 'green-check.png"/>Sync products automatically</li>
                    <li style="padding: 5px 0;display: flex;align-items: center;gap: 5px;"><img style="width: 15px;height: 15px;margin: 5px 5px 0 0;" src="' . $images_path . 'green-check.png"/>Manage stock in real time</li>
                    <li style="padding: 5px 0;display: flex;align-items: center;gap: 5px;"><img style="width: 15px;height: 15px;margin: 5px 5px 0 0;" src="' . $images_path . 'green-check.png"/>Accept Square Gift Card</li>
                    <li style="padding: 5px 0;display: flex;align-items: center;gap: 5px;"><img style="width: 15px;height: 15px;margin: 5px 5px 0 0;" src="' . $images_path . 'green-check.png"/>Boost sales with Express Checkout & more</li>
                </ul>
                <p style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">Ready to sell smarter? 🚀</p>
                <a href="https://wcshopsync.com/pricing/" style="width: 111px; background:#000;color:#fff;border:1px solid #997ab6;padding:8px 15px;border-radius:5px;text-decoration:none;font-size:14px;display:flex;width:fit-content;align-items: center;gap: 5px;"> Upgrade Now 
                    <img style=" width: 15px; height: 12px; margin: 5px 0px 0 10px;" src="' . $images_path . 'arrow-right.png"/>
                </a>
            </td>
            <td style="width: 40%;padding: 26px 20px;">
                <img src="' . $images_path . 'footer-image.png" style="width: 100%;"/>
            </td>
        </tr>
    </table>
</div>';
