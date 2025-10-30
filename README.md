# 🎢 Aurora Box - Theme Park Booking System

A comprehensive web application for booking tickets to theme parks, aqua parks, museums, and nature parks in the Philippines.

## ✨ Features

### For Customers
- 🔍 **Park Discovery** - Browse theme parks, aqua parks, museums, and nature parks
- 🎫 **Online Booking** - Easy ticket booking with multiple payment options
- 🔐 **Google Authentication** - Quick login with Google account
- 💌 **Email Notifications** - Booking confirmations and updates via email
- 📱 **Responsive Design** - Mobile-friendly interface
- 🛒 **Shopping Cart** - Add multiple tickets and manage bookings
- ⭐ **Reviews & Ratings** - Rate and review parks
- 🎁 **Promo Codes** - Apply discounts and promotional offers
- 💬 **Customer Support** - Built-in chat system

### For Vendors/Park Operators
- 📊 **Vendor Dashboard** - Manage park listings and bookings
- 💰 **Revenue Analytics** - Track sales and booking statistics
- 🎫 **Ticket Management** - Set pricing and manage ticket types
- 📅 **Schedule Management** - Control park availability
- 🏷️ **Promo Management** - Create and manage promotional campaigns

### For Administrators
- 👥 **User Management** - Manage customers and vendors
- 📈 **Analytics Dashboard** - System-wide statistics and reports
- 🏞️ **Park Management** - Approve and manage park listings
- 💸 **Transaction Management** - Monitor all payments and refunds
- 📧 **Email Management** - Bulk email campaigns

## 🛠️ Tech Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Authentication**: Google OAuth 2.0
- **Email**: PHPMailer with SMTP
- **Payment**: PayPal integration
- **Dependencies**: Composer

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer
- Web server (Apache/Nginx)
- SSL certificate (for production)

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/aurora-box.git
cd aurora-box
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Setup
Create a `.env` file in the root directory:
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=aurora_box
DB_USER=your_username
DB_PASS=your_password

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost/aurora-box/g-callback.php

# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password

# PayPal Configuration
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
PAYPAL_MODE=sandbox # or live for production
```

### 4. Database Setup
1. Create a MySQL database named `aurora_box`
2. Import the database schema (if you have a SQL file)
3. Update database credentials in your `.env` file

### 5. Google OAuth Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable Google+ API
4. Create OAuth 2.0 credentials
5. Add your domain to authorized origins
6. Update `.env` with your Google credentials

### 6. Web Server Configuration
Point your web server document root to the project directory.

For Apache, create a `.htaccess` file:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

## 📁 Project Structure

```
aurora-box/
├── admin/              # Admin panel files
├── css/               # Stylesheets
├── images/            # Static images
├── modal/             # Modal components
├── navbar/            # Navigation components
├── php/               # PHP utilities
├── scripts/           # JavaScript files
├── uploads/           # User uploaded files
├── VENDOR/            # Vendor dashboard
├── vendor/            # Composer dependencies (excluded from git)
├── config.php         # Main configuration
├── database.php       # Database connection
├── index.php          # Main entry point
├── composer.json      # PHP dependencies
├── .env.example       # Environment variables template
└── README.md          # This file
```

## 🔧 Configuration

### Email Setup
Configure SMTP settings in your `.env` file for:
- Booking confirmations
- Password resets
- Promotional emails

### Payment Gateway
Currently supports PayPal. Configure PayPal credentials in `.env` file.

### Google Maps (Optional)
For park location features, add Google Maps API key to your configuration.

## 🎯 Usage

### For Customers
1. Visit the homepage
2. Browse available parks
3. Select tickets and dates
4. Login with Google or create account
5. Complete booking and payment

### For Vendors
1. Access vendor dashboard at `/VENDOR/`
2. Login with vendor credentials
3. Manage park listings and bookings
4. View analytics and reports

### For Administrators
1. Access admin panel at `/admin/`
2. Login with admin credentials
3. Manage users, parks, and system settings

## 🛡️ Security

- Environment variables for sensitive data
- SQL injection protection
- CSRF protection
- Input validation and sanitization
- Secure session management

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Support

For support and questions:
- Email: support@aurorabox.com
- Documentation: [Wiki](https://github.com/yourusername/aurora-box/wiki)
- Issues: [GitHub Issues](https://github.com/yourusername/aurora-box/issues)

## 🙏 Acknowledgments

- Google APIs for authentication
- PHPMailer for email functionality
- PayPal for payment processing
- All the amazing parks featured on our platform

---

Made with ❤️ for adventure seekers in the Philippines 🇵🇭
