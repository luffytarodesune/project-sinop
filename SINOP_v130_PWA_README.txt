SINOP v130 — iPhone / PWA deployment

Upload these files together in the same GitHub Pages folder:
- SINOP_v130_iphone14_ux_pwa.html
- sinop-manifest.webmanifest
- sinop-sw.js
- sinop-icon-180.png
- sinop-icon-192.png
- sinop-icon-512.png

For iPhone notifications:
1. Open the hosted HTTPS SINOP page on the iPhone.
2. Add SINOP to the Home Screen.
3. Open SINOP from its Home Screen icon.
4. Go to Customize > Bill notifications.
5. Tap Enable phone notifications and allow the iOS prompt.
6. Tap Send test notification.

The service worker can display real system notifications and includes a Web Push receiver. Automatic alerts while SINOP is completely closed require a server/push sender to send Web Push events to the device subscription; a static GitHub Pages page cannot originate those background pushes by itself.
