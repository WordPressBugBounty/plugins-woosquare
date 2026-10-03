<?php
/**
 * Failed email template for Square connection alerts.
 *
 * @package Woosquare_Plus
 * @subpackage Square_Connection
 */

$body = '<div class="container" style="max-width: 700px;margin: 0 auto;width: 100%;">
    <table cellspacing="0" style="width: 100%; background:#FAFBFB; font-family: "Sora", sans-serif; border-spacing: 0;">
        <!-- -->
        <tr style="background: #FC605B;">
            <td style="width: 60%; padding: 26px 36px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img style="width: 20px; height: 20px; float: left; margin: 5px 10px 0 0;" src="' . $images_path . 'check-white.png"/>
                    <h1 style="margin: 0; font-weight: 600; font-size: 16px; color: #fff;">Square Connection Alert <span style="display: block; font-size: 12px; font-weight: 400;">Connection Successful</span></h1>
                </div>
            </td>
            <td style="width: 40%; padding: 26px 36px; text-align: right;">
                <img src="' . $images_path . 'wc-emal-logo.png"/>
            </td>
        </tr>
        <!-- -->
        <tr style="background: #FEF8F3;">
            <td colspan="2" style="padding: 22px;">
                <h2 style="font-size: 16px; color: #1D1D1B; font-weight: 600; margin: 0 0 5px 0;">Unable to establish connection with Square API</h2>
                <p style="font-size: 14px; color: #1D1D1B; font-weight: 400;  margin: 0;">The connection attempt failed. please review the details below.</p>
            </td>
        </tr>
        <!-- -->
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
                            <p style="margin:0;font-weight:700; color:#FC605B;float: right;">
                                <img src="' . $images_path . 'cross.png" style="width:16px;height:16px;margin: 1px 6px 0 0;float: left;"/>
                                Disconnected
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
        <!-- -->
         <tr style="background:#FAFBFB;padding:30px 22px">
            <td colspan="2" style=" padding: 30px 30px;">
                <div style="">
                    <h3 style="margin: 0; color: #1D1D1B;">Request Details</h3>
                    <p style="margin: 0; color: #5E6673; ">Request Body</p>
                    <div style="background: #fff; padding: 10px; border: 0.8px solid #CCE0FF; margin: 10px 0;">
                        Request body
                    </div>
                </div>
            </td>
        </tr>
         <!-- -->
        <tr style="background: #FFF0EA;">
            <td style="width: 60%; padding: 30px 30px;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <img src="' . $images_path . 'info.png" style="width:25px;height:25px;margin: 0px 6px 0 0;float: left;"/>    
                Action Required
                </h3>
                <ul>
                    <li style="padding-bottom: 10px;">Verify your Square API Credentials</li>
                    <li style="padding-bottom: 10px;">Try reconnecting Square. <a style="color: #A61B1B; text-decoration: underline;" href="https://apiexperts.io/documentation/woosquare-plus/#getting-started-7">How to reconnect Square with WC Shop Sync</a></li>
                </ul>
            </td>
            <td style="width: 60%; padding: 30px 30px;">

            </td>
        </tr>
    </table>
</div>';
