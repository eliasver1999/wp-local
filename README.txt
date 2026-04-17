=== Personalized Cards Creator ===
Contributors: yourname
Tags: cards, personalized, email, wallet, custom
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create personalized cards with email delivery and digital wallet integration (Apple Wallet & Google Wallet).

== Description ==

Personalized Cards Creator allows you to create beautiful personalized cards for your members. 

**Key Features:**

* 🎨 Create personalized cards with custom templates
* 📧 Automatic email delivery with card attachments
* 📱 Apple Wallet integration
* 📱 Google Wallet integration
* 👥 2-year membership management
* 📊 Admin dashboard with statistics
* 🎯 Multiple subscription levels (Basic, Premium, VIP)
* 🔒 Secure and user-friendly

**Perfect For:**

* Membership cards
* Gift cards
* Event passes
* Loyalty cards
* Greeting cards
* Business cards

== Installation ==

1. **Upload the Plugin:**
   - Download the plugin zip file
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin" and select the zip file
   - Click "Install Now" and then "Activate"

2. **Add Template Images:**
   - Navigate to `/wp-content/plugins/personalized-cards/templates/cards/`
   - Upload your JPEG card templates
   - Name them: `template-basic.jpg`, `template-premium.jpg`, etc.

3. **Add Font (Optional but Recommended):**
   - Navigate to `/wp-content/plugins/personalized-cards/assets/fonts/`
   - Upload a TrueType font file (e.g., arial.ttf)
   - Download free fonts from Google Fonts: https://fonts.google.com/

4. **Configure Plugin:**
   - Go to WordPress Admin → Personalized Cards → Settings
   - Configure email settings
   - (Optional) Configure wallet integration

5. **Grant Memberships:**
   - Go to Users → All Users
   - Edit a user
   - Scroll to "Personalized Cards Membership"
   - Check "Enable 2-year membership"
   - Save

6. **Add Shortcodes to Pages:**
   - Create a new page for card creation
   - Add shortcode: [personalized_cards_form]
   - Create another page for viewing cards
   - Add shortcode: [my_personalized_cards]

== Configuration ==

**Email Settings:**

1. Go to Personalized Cards → Settings
2. Set "From Name" (e.g., Your Company Name)
3. Set "From Email" (e.g., noreply@yoursite.com)
4. Customize email subject and message
5. Use placeholders: {name} for user name, {site_name} for site name

**For Better Email Delivery:**

Install WP Mail SMTP plugin for reliable email delivery:
- Download: https://wordpress.org/plugins/wp-mail-smtp/
- Configure with Gmail, SendGrid, or other SMTP service

**Apple Wallet Setup (Advanced):**

1. Sign up for Apple Developer account ($99/year)
2. Create Pass Type ID at https://developer.apple.com/
3. Generate certificates
4. Export as .pem files
5. Place in `/wp-content/plugins/personalized-cards/certificates/`
6. Enable in plugin settings

**Google Wallet Setup (Advanced):**

1. Create Google Cloud project
2. Enable Google Wallet API
3. Create service account
4. Download credentials JSON
5. Place in `/wp-content/plugins/personalized-cards/certificates/`
6. Get Issuer ID from Google Wallet Console
7. Add to plugin settings

== Usage ==

**For Administrators:**

1. **View Dashboard:**
   - Go to Personalized Cards in admin menu
   - View statistics and recent cards

2. **Manage Memberships:**
   - Edit any user profile
   - Toggle membership on/off (grants 2 years)
   - View expiry date

3. **View All Cards:**
   - Go to Personalized Cards → All Cards
   - Download or delete cards

4. **Settings:**
   - Go to Personalized Cards → Settings
   - Configure all plugin options

**For Members:**

1. **Create a Card:**
   - Visit the card creation page
   - Select a template
   - Enter name and message
   - Choose to send via email
   - Click "Create Card"

2. **Download Card:**
   - Card displays immediately
   - Click "Download Card" button
   - Save to device

3. **Add to Wallet:**
   - Click "Add to Apple Wallet" (iPhone)
   - Click "Add to Google Wallet" (Android)

4. **View My Cards:**
   - Visit "My Cards" page
   - See all previously created cards
   - Download any card

== Testing the Plugin ==

**Step 1: Local Setup**

1. Install local WordPress (use Local by Flywheel, XAMPP, or MAMP)
2. Install and activate the plugin
3. Add template images to templates/cards/
4. Add a TrueType font to assets/fonts/

**Step 2: Configure**

1. Go to Personalized Cards → Settings
2. Set email settings
3. Save settings

**Step 3: Test User**

1. Create a test user
2. Edit test user
3. Enable 2-year membership
4. Save

**Step 4: Create Card**

1. Login as test user
2. Go to card creation page
3. Create a card
4. Verify:
   - Card is created
   - Image displays
   - Download works
   - Email is received (check spam)

**Step 5: Admin Testing**

1. Login as admin
2. Check dashboard statistics
3. View all cards
4. Test delete functionality

**Email Testing Tools:**

- MailHog (local email testing): https://github.com/mailhog/MailHog
- Mailtrap (email sandbox): https://mailtrap.io/
- WP Mail SMTP plugin with Gmail

== Shortcodes ==

**[personalized_cards_form]**
Displays card creation form for logged-in members.

**[my_personalized_cards]**
Displays grid of user's created cards.

== Frequently Asked Questions ==

= Do I need coding knowledge? =
No! Just upload templates, configure settings, and use shortcodes.

= What image format should templates be? =
JPEG format (.jpg). Recommended size: 800x500 pixels or larger.

= Can I customize card designs? =
Yes! Create your own JPEG templates in Photoshop, Canva, or any design tool.

= How do I grant memberships? =
Edit user → Check "Enable 2-year membership" → Save.

= Can I change membership duration? =
Yes, edit the code in includes/admin/user-subscription.php (line with '+2 years').

= Does this work with WooCommerce? =
The plugin works independently but can be integrated with WooCommerce with custom code.

= Do I need Apple/Google Wallet? =
No, wallet features are optional. The plugin works perfectly without them.

= Can I send cards to other email addresses? =
Currently sends to user's registered email. Custom recipients require code modification.

== Screenshots ==

1. Card creation form
2. Admin dashboard with statistics
3. User membership settings
4. Email settings page
5. Created card with wallet options
6. My cards grid view

== Changelog ==

= 2.0.0 =
* Initial release
* Card creation with templates
* Email delivery
* Apple Wallet integration
* Google Wallet integration
* Admin dashboard
* 2-year membership system
* Multiple subscription levels

== Upgrade Notice ==

= 2.0.0 =
Initial release of Personalized Cards Creator.

== Support ==

For support, please contact your developer or visit the plugin documentation.

== Credits ==

Developed with ❤️ for WordPress community.

== License ==

This plugin is licensed under GPLv2 or later.
