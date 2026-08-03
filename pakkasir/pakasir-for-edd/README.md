# Pakasir for Easy Digital Downloads

- Contributors: hdrxs312
- Tags: payment gateway, qris, edd
- Requires at least: 4.7
- Tested up to: 6.8
- Stable tag: 1.0.0
- Requires PHP: 7.0
- License: GPLv2 or later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html
- Pakasir Payment Gateway (QRIS, Virtual Account, etc) for EDD. (compatible with Indonesia banks/e-wallets only)

## External services

This plugin connects to Pakasir API to create a payment link and check it's status. Once the payment status become paid, it will make the related Easy Digital Downloads order become completed.

It doesn't send any user's data. It's only send the order id and the total amount needs to be paid.

This service is provided by "Pakasir": [terms of use](https://pakasir.com/p/tos), [privacy policy](https://pakasir.com/p/privacy).

## Prerequisite

This plugin requires Easy Digital Downloads installed and active in your Wordpress.

Also, it needs an active Pakasir project. If you didn't have it yet, please create one at [Pakasir website](https://app.pakasir.com/projects)

After creating a project, please save the `slug` and the `api key`. You will need it.

## How to install

1. After installing and activating the plugin, you can click the Manage link under the Plugin name, in the "Installed Plugins" page.

2. Paste the `slug` and `api key` that you have from the Pakasir website. (In the project detail page).

[See image here](https://github.com/user-attachments/assets/afa4a85a-83fd-4e8f-92e7-d4ff8405e7b1)


## Add webhook to Pakasir

1. Open your project in the [Pakasir website](https://app.pakasir.com/projects). Go to the project detail page.

2. Click `Edit Proyek` button, input the following webhook URL:
```
https://example.com/?edd-listener=pakasir
```
_Replace `example.com` with you actual wordpress domain._

[See image here](https://github.com/user-attachments/assets/ad1baa48-b250-4f0b-932f-9f49f1db85ef)

## Resources

- [Pakasir docs](https://pakasir.com/p/docs)

