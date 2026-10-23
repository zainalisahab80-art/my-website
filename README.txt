ZENTIC WEBSITE
==============
Needs hosting WITH PHP (almost every cPanel / shared host has it).
Netlify / GitHub Pages / Vercel are static-only: they would show the site but NOT the admin, settings or ratings.

UPLOAD
------
Upload everything inside this folder to public_html (index.html must be in the root).
Folders  data/  and  images/  must be writable (permission 755, or 775 if uploads fail).

ADMIN DASHBOARD
---------------
Open: yourwebsite.com/admin.php   (or "Admin Login" at the bottom of any page)
Username: zentic     Password: zenticadmin
>>> After the first login go to the "Password" tab and change the password. <<<

Tabs:
 Overview        counts + average rating
 Designs         upload / edit / delete posters and designs (with client name + category)
 Clients & Logos upload / edit / delete client logos (shown in "Our clients" on Home)
 Ratings         see every visitor rating, delete spam
 Settings        email, WhatsApp number, phone, first WhatsApp message, social links (Facebook, Instagram, TikTok, YouTube, LinkedIn)
 Password        change admin password

Social links left empty are hidden from the footer. WhatsApp, Email and Call icons are always shown.

LIVE RATINGS
------------
Visitors rate with stars on the Home page (ratings.php). Their rating shows instantly.
One rating per visitor per hour. Delete any entry from the admin "Ratings" tab.

FLOATING BUTTONS
----------------
WhatsApp (green) on top, blue chat button below it. The chat opens WhatsApp with the visitor's message.
