<?php
$msg_template = <<<EOT
<html>
<head>
%s
</head>
<body>
<center>
<div>
<h1>%s</h1>
</div>
<div style="text-align: center">
%s
</div>
<br />
<div>
    <a class='ct_settings_button' href='http://%s/wp-admin/%s.php?page=cleantalk&from_report=1' target="_blank">%s</a>
</div>
<span>%s</span>
<br />
<br />
<div style="color: #666;">
    The report is provided by <a href="http://%s/wp-admin/%s.php?page=cleantalk&from_report=1">%s</a>.
</div>
</center>
</body>
</html>
EOT;

$style = <<<EOT
<meta charset="utf-8"> <!-- utf-8 works for most cases -->
<meta name="viewport" content="width=device-width"> <!-- Forcing initial-scale shouldn't be necessary -->
<meta http-equiv="X-UA-Compatible" content="IE=edge"> <!-- Use the latest (edge) version of IE rendering engine -->
<title></title> <!-- The title tag shows in email notifications, like Android 4.4. -->

<!-- Web Font / @font-face : BEGIN -->
<!-- NOTE: If web fonts are not required, lines 9 - 26 can be safely removed. -->

<!-- Desktop Outlook chokes on web font references and defaults to Times New Roman, so we force a safe fallback font. -->
<!--[if mso]>
    <style>
        * {
            font-family: sans-serif !important;
        }
    </style>
<![endif]-->

<!-- All other clients get the webfont reference; some will render the font and others will silently fail to the fallbacks. More on that here: http://stylecampaign.com/blog/2015/02/webfont-support-in-email/ -->
<!--[if !mso]><!-->
    <!-- insert web font reference, eg: <link href='https://fonts.googleapis.com/css?family=Roboto:400,700' rel='stylesheet' type='text/css'> -->
<!--<![endif]-->

<!-- Web Font / @font-face : END -->

<!-- CSS Reset -->
<style>
    /* What it does: Remove spaces around the email design added by some email clients. */
    /* Beware: It can remove the padding / margin and add a background color to the compose a reply window. */
    html,
    body {
        margin: 0 auto !important;
        padding: 0 !important;
        height: 100% !important;
        width: 100% !important;
    }
    
    /* What it does: Stops email clients resizing small text. */
    * {
        -ms-text-size-adjust: 100%;
        -webkit-text-size-adjust: 100%;
    }
    
    /* What is does: Centers email on Android 4.4 */
    div[style*="margin: 16px 0"] {
        margin:0 !important;
    }
    
    /* What it does: Stops Outlook from adding extra spacing to tables. */
    table,
    td {
        mso-table-lspace: 0pt !important;
        mso-table-rspace: 0pt !important;
    }
            
    /* What it does: Fixes webkit padding issue. Fix for Yahoo mail table alignment bug. Applies table-layout to the first 2 tables then removes for anything nested deeper. */
    table {
        border-spacing: 0 !important;
        border-collapse: collapse !important;
        table-layout: fixed !important;
        margin: 0 auto !important;
    }
    table table table {
        table-layout: auto; 
    }
    
    td {
        border: 1px solid #ccc;
		text-align: center;
    }

    /* What it does: Uses a better rendering method when resizing images in IE. */
    img {
        -ms-interpolation-mode:bicubic;
    }
    
    /* What it does: A work-around for iOS meddling in triggered links. */
    .mobile-link--footer a,
    a[x-apple-data-detectors] {
        color:inherit !important;
        text-decoration: underline !important;
    }
	.ct_settings_button{
		display: inline-block;
		margin: 20px;
		padding: 12px 24px;
		border: 1px solid #216298;
		border-radius: 8px;
		background: #35a0f7;
		background: -webkit-gradient(linear, left top, left bottom, from(#35a0f7), to(#216298));
		background: -moz-linear-gradient(top, #35a0f7, #216298);
		background: linear-gradient(to bottom, #35a0f7, #216298);
		text-shadow: #153e5f 1px 1px 1px;
		font: normal normal bold 16px verdana;
		color: #ffffff;
		text-decoration: none;
	}
	.ct_type{
		font-weight: 300;
		text-align: center;
		border: 1px solid #ccc;
		padding: 3px;
	}
	.ct_hat{
		border: 2px solid #ccc;
		padding: 5px;
	}
	.allowed_hat{
		color: green;
	}
	.blocked_hat{
		color: red;
	}
  
</style>
EOT;

$events = <<<EOT
<table width="400px" border="1" padding="1" style="margin: 0 auto;">
	<thead>
		<tr>
			<th class="ct_hat">
			   Type
			</th>
			<th class="ct_hat allowed_hat">
				Allowed
			</th>
			<th class="ct_hat blocked_hat">
				Blocked
			</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class="ct_type">Comments</td>
			<td>%u</td>
			<td>%u</td>
		</tr>
		<tr>
			<td class="ct_type">Registrations</td>
			<td>%u</td>
			<td>%u</td>
		</tr>
		<tr>
			<td class="ct_type">Contacts</td>
			<td>%u</td>
			<td>%u</td>
		</tr>
	</tbody>
</table>
EOT;
?>
