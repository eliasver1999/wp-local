CERTIFICATES FOLDER
====================

This folder holds Apple Wallet and Google Wallet credentials.
You normally upload these from the plugin's Settings page (recommended) — doing so
writes them here with the exact filenames below. You can also drop them in manually.

APPLE WALLET:
-------------
Required filenames (uploaded via Settings → Upload Apple Wallet Certificates):
- apple-certificate.p12  (Your Pass Type ID signing certificate, exported from Keychain as .p12)
- apple-wwdr.pem         (Apple Worldwide Developer Relations certificate, converted to PEM)

The .p12 password is stored as a plugin setting (Settings → Digital Wallet), not here.
Convert the WWDR .cer to .pem with:
  openssl x509 -inform der -in AppleWWDRCAG4.cer -out apple-wwdr.pem

Get from: https://developer.apple.com/

GOOGLE WALLET:
--------------
Required filename (uploaded via Settings → Upload Google Wallet Service Account Key):
- google-wallet-key.json  (Google Cloud service account key with the Google Wallet API enabled)

Get from: https://console.cloud.google.com/

NOTE: Wallet integration is OPTIONAL.
The plugin works perfectly without these files.
These files are secrets — keep them out of version control and public access.
