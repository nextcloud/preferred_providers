# Preferred providers

This application allows external request of new accounts.

1. Install and enable the application.
2. Go to the preferred providers settings and keep your token in reach.
3. Make a POST request to `/ocs/v2.php/account/request/YOURTOKEN` with the `{email: 'myawesomemail@nextcloud.com'}` data.

``` js
$.post('/ocs/v2.php/account/request/56300a2bf7e06894a5b59c1eb47f7460', {email:'myawesomemail@nextcloud.com'}).complete((response) => {
    console.log(JSON.parse(response.responseText).data.setPassword)
})
```

4. The server will accept or not the request and provide a link for the user login and password definition https://cloud.yourdomain.com/apps/preferred_providers/password/set/yourawesomemail@nextcloud.com/aipTgstNeenUXe20BJTH8
5. Meanwhile a mail confirmation is sent to the user. He have 6h to confirm or his account will be disabled
6. After 4, if you set up the `OCS-APIREQUEST` header, you will be redirected to a `nc://` url with valid app-password token for your application. If not, you will be logged and redirected to the home page.
