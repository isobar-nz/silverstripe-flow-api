<% with $Order %><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
    <title>{$Title}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body>
<table style="height:100%!important;width:100%!important;border-spacing:0;border-collapse:collapse">
    <tbody>
    <tr>
        <td
                style="font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,&quot;Roboto&quot;,&quot;Oxygen&quot;,&quot;Ubuntu&quot;,&quot;Cantarell&quot;,&quot;Fira Sans&quot;,&quot;Droid Sans&quot;,&quot;Helvetica Neue&quot;,sans-serif">
            <table style="width:100%;border-spacing:0;border-collapse:collapse;margin:40px 0 20px">
                <tbody>
                <tr>
                    <td
                            style="font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,&quot;Roboto&quot;,&quot;Oxygen&quot;,&quot;Ubuntu&quot;,&quot;Cantarell&quot;,&quot;Fira Sans&quot;,&quot;Droid Sans&quot;,&quot;Helvetica Neue&quot;,sans-serif">
                        <center>
                            <table style="width:560px;text-align:left;border-spacing:0;border-collapse:collapse;margin:0 auto">
                                <tbody>
                                <tr>
                                    <td
                                            style="font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,&quot;Roboto&quot;,&quot;Oxygen&quot;,&quot;Ubuntu&quot;,&quot;Cantarell&quot;,&quot;Fira Sans&quot;,&quot;Droid Sans&quot;,&quot;Helvetica Neue&quot;,sans-serif">
                                        <table style="width:100%;border-spacing:0;border-collapse:collapse">
                                            <tbody>
                                            <tr>
                                                <td align="right"
                                                    style="font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,&quot;Roboto&quot;,&quot;Oxygen&quot;,&quot;Ubuntu&quot;,&quot;Cantarell&quot;,&quot;Fira Sans&quot;,&quot;Droid Sans&quot;,&quot;Helvetica Neue&quot;,sans-serif;text-transform:uppercase;font-size:14px;color:#999">
                                                    <span style="font-size:16px">{$Title}</span>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </center>
                    </td>
                </tr>
                </tbody>
            </table>

            <table
                    style="width:100%;border-spacing:0;border-collapse:collapse;border-top-width:1px;border-top-color:#e5e5e5;border-top-style:solid">
                <tbody>
                <tr>
                    <td
                            style="font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,&quot;Roboto&quot;,&quot;Oxygen&quot;,&quot;Ubuntu&quot;,&quot;Cantarell&quot;,&quot;Fira Sans&quot;,&quot;Droid Sans&quot;,&quot;Helvetica Neue&quot;,sans-serif;padding:40px 0">
                        <center>
                            <table style="width:560px;text-align:left;border-spacing:0;border-collapse:collapse;margin:0 auto">
                                <tbody>
                                <tr>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,&quot;Roboto&quot;,&quot;Oxygen&quot;,&quot;Ubuntu&quot;,&quot;Cantarell&quot;,&quot;Fira Sans&quot;,&quot;Droid Sans&quot;,&quot;Helvetica Neue&quot;,sans-serif">
                                        <h3 style="font-weight:normal;font-size:20px;margin:0 0 25px">
                                            <p>Errors have been detected during the import of products from Flow.<br></p>

                                            <div style="font-size: 12px;">
                                                {$Errors}
                                            </div>

                                            <p><br><a href="{$ViewMoreLink}">Click here to go to the CMS</a></p>
                                        </h3>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </center>
                    </td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>
</body>
</html>
<% end_with %>
