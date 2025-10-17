# Gate Code System - Features Documentation

## Overview
A comprehensive community gate code management system with PIN-based authentication, GPS location search, contribution management, and administrative controls.

---

## 🔐 Authentication & Security

### Role-Based Authentication System
- **User Roles**:
  - **User**: Access to public search and contribution features only
  - **Admin**: Full access to admin dashboard and all management features
- **Admin Login** (admin/login.php):
  - PIN-based authentication for admin access
  - Role verification (admin role required)
  - Session-based authentication
  - "Forgot PIN?" recovery option
  - Secure password masking
  - Error handling and validation
  - Back to home link
- **PIN Recovery** (admin/forgot-pin.php):
  - Email-based PIN recovery system
  - PHPMailer integration for email delivery
  - Beautiful HTML email template
  - Security notice in email
  - Email address validation
  - User lookup by email
  - SMTP configuration support
- **Session Management**:
  - PHP session-based authentication
  - Role stored in session ($_SESSION['user_role'])
  - Automatic role verification on each request
  - Session destruction on logout
- **Logout System** (admin/logout.php):
  - Secure session destruction
  - Cookie cleanup
  - Redirect to login with success message
  - Logout button in sidebar
- **Authentication Methods**:
  - Session-based (primary): Check $_SESSION['admin_authenticated']
  - URL Key (backward compatible): `?key=43982` still works
  - Role-based access control for admin pages

### User Management
- **Enhanced User Registration**:
  - Name, Email, Role, and PIN fields
  - Email validation (FILTER_VALIDATE_EMAIL)
  - Unique PIN generation with memorable patterns
  - Unique email enforcement
  - Role dropdown: User or Admin
  - Auto-generated 5-digit PINs
- **User Display**:
  - Role badges (Admin/User) with gradient colors
  - Email display with icon
  - Registration date tracking
  - PIN click-to-copy (shows "Copied" for 2 seconds)
  - Search by name, email, role, or PIN

---

## 🔍 Public Features (index.php)

### Search & Discovery
- **Community Search**: Real-time search by community name with accent normalization
- **GPS Location Search**:
  - Find communities within 30 meters of user's location
  - Distance calculation using Haversine formula
  - Displays closest community with distance in meters
  - Requires GPS coordinates in `gates.json`
- **Search Analytics**: Tracks all searches via `track_search.php`

### Gate Code Display
- **Code Details Modal**:
  - Community name and city
  - Gate code (monospace font)
  - Notes and additional information
  - Photo display with fallback to default thumbnail
  - Report count badge (!) for problematic codes
- **HTTP Gate Control**: Direct gate opening via HTTP URL (when available)
- **Report System**: Users can report incorrect/outdated codes with categorized reasons

### Photo Contribution System
- **Update Photo Feature**:
  - Only visible for default photos (`thumbnailnone.png`)
  - Hidden if pending suggestion already exists
  - Mobile camera integration with `capture="environment"`
  - Image compression (max 1200px, 80% JPEG quality)
  - Automatic GPS extraction from EXIF metadata
  - Upload to `temp_assets/` for admin approval
  - Saves to `suggest.json` with type `photo_update`
  - Validates that only one pending update exists per gate code

### User Interface
- **Theme Toggle**:
  - Dark/Light mode switching
  - Persistent preference via localStorage
  - Default to dark mode
  - Smooth theme transitions
- **Responsive Design**:
  - Mobile-first approach
  - Adaptive layouts for mobile (max-width: 480px)
  - Touch-friendly button sizes
  - Optimized modal displays
- **User Info Display**:
  - Avatar with initials
  - Username display
  - Masked PIN (last 2 digits visible)
  - Logout button
  - Fixed position on desktop, static on mobile

---

## 📝 Contribution Submission (submit.php)

### Community Contribution
- **Add New Communities**: Users can submit new gate code communities
- **Multi-Code Support**: Add multiple gate codes per community
- **Photo Upload**:
  - Styled file upload button with camera icon
  - Image compression before upload
  - GPS extraction from EXIF data
  - Upload to `temp_assets/` directory
  - Unique filename generation with timestamp
- **Form Fields**:
  - Community Name (required)
  - City Name
  - Gate Codes with notes
  - Photos with coordinates
- **Contribution Tracking**:
  - Saves to `suggest.json` with timestamp
  - Includes submitter name and PIN
  - Type: `new_contribution`

### Dynamic Code Management
- **Add Codes**: Dynamic code row addition
- **Remove Codes**: Delete code entries with auto-renumbering
- **Proper Code Numbering**: Uses array length instead of incrementing counter
- **GPS Coordinates**: Attached to each code from photo metadata

---

## 👨‍💼 Admin Panel

### Dashboard (admin/index.php)
- **System Statistics**:
  - Total communities count
  - Total gate codes count
  - Pending contributions count
  - Total users count
  - Recent search activity
- **Quick Actions**: Direct links to main admin functions
- **Version Display**: Current build version

### Community Management (admin/communities.php)
- **View All Communities**:
  - Sortable table display
  - City information
  - Code count per community
  - Last update timestamp
- **Add New Community Modal**:
  - Community and city fields
  - Multiple gate code entries
  - Photo upload for each code
  - GPS coordinate extraction
  - Dynamic code numbering (restarts correctly after deletions)
- **Edit Community Modal**:
  - Update community details
  - Edit existing gate codes
  - Add new codes to existing communities
  - Delete codes with silent removal (no alert modal)
  - Photo management per code
  - Warning icon for reported codes
  - Proper code number update after deletion
- **Delete Communities**: Remove entire community entries
- **HTTP URL Configuration**: Set gate control URLs per community

### Contribution Review (admin/contributions.php)
- **Review Pending Submissions**:
  - View all suggestions from `suggest.json`
  - Display submitter information
  - Submission timestamp
  - Contribution type (new_contribution, photo_update)
- **Approve Contributions**:
  - **New Contributions**: Adds community/codes to `gates.json`
  - **Photo Updates**:
    - Updates existing gate code photo
    - Moves image from `temp_assets/` to `assets/`
    - Renames with community name pattern
    - Updates GPS coordinates at community level
  - Removes from `suggest.json` after approval
- **Reject Contributions**: Delete from suggestion queue
- **Modal Scroll**: Scrollable content for long contributions
- **Responsive Layout**:
  - Side-by-side GPS coordinates in mobile
  - Side-by-side community/city fields in mobile
  - Footer spacing (20px desktop, 30px mobile)

### User Management (admin/users.php)
- **User List**:
  - Display all users from `data/pin.json`
  - Role badges (Admin/User) with distinct colors
  - Email and registration date display
  - PIN click-to-copy functionality
  - Search by name, email, role, or PIN
- **Add New User**:
  - Name field (required)
  - Email field with validation (required)
  - Role dropdown: User or Admin (required)
  - PIN auto-generation with memorable patterns
  - Duplicate email prevention
  - Duplicate PIN prevention
- **Edit Users**:
  - Update name, email, and role
  - Change PINs with uniqueness check
  - Email validation on update
  - Role modification (User ↔ Admin)
- **Delete Users**: Remove user access with confirmation
- **User Activity**: Track user search history
- **User History**: View individual user search patterns

### Gallery Management (admin/gallery.php)
- **Image Overview**:
  - View all uploaded images from `assets/` directory
  - Display community labels for each image
  - Grid layout with responsive design
  - Image statistics (count and total size)
- **Upload Images**:
  - Drag & drop support
  - Mobile camera integration
  - Image compression (max 1200px, 80% quality)
  - GPS extraction from EXIF metadata
  - Preview before upload
- **Delete Images**:
  - System modal confirmation
  - Automatic gates.json update
  - Replaces deleted image references with default thumbnail
  - Shows count of affected gate codes
- **Download Images**: Direct download functionality
- **Scroll Container**: Proper scroll with footer clearance

### Settings (admin/settings.php)
- **Admin Only Access**:
  - Role-based authentication (`role: 'admin'` in pin.json)
  - Access denied redirect for non-admin users
  - "ADMIN" badge in page header
- **Administrator Email**:
  - Primary contact email configuration
  - Email validation (optional field)
  - Used for system notifications and alerts
- **Timezone Configuration**:
  - Dropdown selector with US and international timezones
  - US: ET, CT, MT, MST, PT, AKT, HST
  - International: UTC, London, Paris, Madrid, Tokyo, Shanghai, Sydney
  - Live server time display (updates every second)
- **Date Format Selection**:
  - 5 radio button options with live examples:
    - `Y-m-d H:i:s` (2025-01-15 14:30:00)
    - `M d, Y g:i A` (Jan 15, 2025 2:30 PM)
    - `d/m/Y H:i` (15/01/2025 14:30)
    - `m/d/Y h:i A` (01/15/2025 02:30 PM)
    - `F j, Y, g:i a` (January 15, 2025, 2:30 pm)
  - Visual examples update dynamically
- **Settings Persistence**:
  - Stored in `data/settings.json`
  - Tracks last update timestamp and user
  - Form validation and error handling
  - Success/error modal notifications

### Backup System (admin/backup.php)
- **Database Backup**:
  - Export `gates.json`
  - Export `suggest.json`
  - Export `pin.json`
  - Export user search history
- **Restore Functionality**: Import from backup files
- **Download Backups**: ZIP archives of all data

---

## 📊 Data Management

### Data Files
- **`data/gates.json`**: Main database of communities and gate codes
  - Community information
  - City data
  - Gate codes with notes
  - Photo paths
  - GPS coordinates at community level
  - Report counts
  - HTTP URLs for gate control
  - Submission metadata

- **`data/suggest.json`**: Pending contributions queue
  - New community suggestions
  - Photo update requests
  - Submitter tracking
  - Timestamps
  - Contribution type flags

- **`data/pin.json`**: User authentication database
  - User names
  - Email addresses (unique, required)
  - PINs (5-digit, unique, auto-generated)
  - Roles: 'user' or 'admin'
  - Registration dates (Y-m-d H:i:s format)

- **`data/settings.json`**: System configuration
  - Administrator email address
  - Timezone setting
  - Date format preference
  - Last update metadata (timestamp and user)

- **`data/search_history.json`**: Analytics data
  - Search queries
  - Timestamps
  - User information
  - Search patterns

### File Upload System
- **Temporary Storage**: `temp_assets/` for pending uploads
- **Permanent Storage**: `assets/` for approved photos
- **Naming Convention**: `gate_{community}_{timestamp}_{hash}.{ext}`
- **Supported Formats**: JPEG, JPG, PNG, GIF, WebP
- **Upload Endpoints**:
  - `upload_temp.php`: For contributions (before approval)
  - Photo upload for approved content

---

## 🎨 UI/UX Features

### Responsive Design
- **Breakpoints**:
  - Desktop: Full width
  - Mobile: max-width 480px
- **Adaptive Elements**:
  - User info: Fixed on desktop, static on mobile
  - Theme toggle: Absolute positioning on mobile
  - Form layouts: Side-by-side inputs maintained
  - Modal displays: Scrollable content on overflow

### Visual Design
- **Color Schemes**:
  - Dark Mode (default): Dark backgrounds, bright accents
  - Light Mode: Light backgrounds, subtle gradients
- **Brand Colors**:
  - Primary: #3bdd82 (green)
  - Danger: #ff5c5c (red)
  - Muted: Gray tones
- **Animations**:
  - Smooth theme transitions
  - GPS pulse effect
  - Button hover states
  - Modal fade-in/out
  - Title underline animation

### Accessibility
- **ARIA Labels**: Proper semantic HTML
- **Keyboard Navigation**: Full keyboard support
- **Focus Management**: Clear focus indicators
- **Screen Reader Support**: Descriptive labels and roles
- **Escape Key**: Close modals with ESC
- **Backdrop Clicks**: Close modals by clicking outside

---

## 🔧 Technical Features

### Image Processing
- **Compression**:
  - Canvas-based resizing
  - Max dimension: 1200px
  - Quality: 80% JPEG
- **EXIF Extraction**:
  - GPS latitude/longitude
  - DMS to Decimal Degree conversion
  - Frontend (EXIF.js) and backend (PHP exif_read_data) support
- **Format Support**: Automatic JPEG conversion

### GPS & Location
- **Geolocation API**: Browser-based location access
- **Distance Calculation**: Haversine formula implementation
- **Search Radius**: 30 meters (configurable)
- **High Accuracy Mode**: Enabled for precise location
- **Coordinate Storage**: Community-level GPS data
- **Photo Metadata**: GPS embedded in images

### API Endpoints
- **`auth_pin.php`**: PIN authentication
- **`track_search.php`**: Search analytics logging
- **`report_gate.php`**: Gate code reporting system
- **`suggest_photo_update.php`**: Photo update submission
- **`upload_temp.php`**: Temporary photo upload
- **Admin CRUD operations**: Various endpoints for data management

### Security Measures
- **Session Management**: PHP sessions for authentication
- **Role-Based Access Control (RBAC)**:
  - Two roles: 'user' and 'admin'
  - Admin role required for dashboard access
  - Settings page requires admin role
  - Role verified on every request
  - Automatic redirect for unauthorized access
  - Session-based role storage
- **Input Validation**: Server-side validation
- **XSS Prevention**: HTML escaping on output
- **File Upload Security**:
  - Type validation
  - Size limits
  - Unique naming
  - Separate temp directory
- **PIN Protection**: All routes require authentication
- **Email Validation**:
  - FILTER_VALIDATE_EMAIL for all email inputs
  - Unique email constraint in user system
  - Email-based PIN recovery
- **Authentication Security**:
  - Session hijacking prevention
  - Secure logout with cookie cleanup
  - Password masking in login forms
  - Role verification on sensitive operations

---

## 📱 Mobile Features

### Mobile Optimization
- **Touch-Friendly**: Large tap targets (44px minimum)
- **Camera Integration**:
  - Native camera access
  - `capture="environment"` for rear camera
  - File picker fallback
- **Responsive Inputs**:
  - Full-width on mobile
  - Maintained side-by-side layouts where important
- **Swipe Gestures**: Modal backdrop dismiss
- **Viewport Optimization**: Proper meta viewport configuration

### Progressive Enhancement
- **Offline Graceful Degradation**: Error messages for connectivity issues
- **GPS Fallback**: Manual search available if location denied
- **Image Fallback**: Default thumbnail if photo unavailable
- **No-CORS HTTP Requests**: Gate control works across networks

---

## 🔔 Reporting & Analytics

### Gate Code Reporting
- **Report Reasons**:
  - Code is incorrect
  - Code is outdated/changed
  - Code not working anymore
  - Wrong community assigned
  - Duplicate entry
  - Other reason (custom)
- **Report Tracking**:
  - Count badge display
  - Warning icons in admin
  - Report history
- **Admin Visibility**: Highlighted codes in edit modal

### Search Analytics
- **Tracked Data**:
  - Search queries
  - Search timestamps
  - User information
  - Query frequency
- **History Display**:
  - Recent searches on dashboard
  - User-specific search history
  - Pattern analysis

---

## 🎯 Key Workflows

### User Contribution Flow
1. User authenticates with PIN
2. Navigates to submit.php or clicks "Update Photo"
3. Fills form with community/code/photo data
4. Image compressed and GPS extracted
5. Uploaded to `temp_assets/`
6. Saved to `suggest.json` with type flag
7. Admin reviews in contributions.php
8. On approve:
   - Photo moved to `assets/` with proper naming
   - Data added/updated in `gates.json`
   - Suggestion removed from `suggest.json`

### Photo Update Flow
1. User views gate code with default photo
2. "Update Photo" button appears (if no pending suggestion)
3. Takes photo or selects from gallery
4. GPS extracted from EXIF
5. Image compressed
6. Uploaded to `temp_assets/`
7. Saved to `suggest.json` as `photo_update` type
8. Button hidden until admin processes
9. Admin approves in contributions.php
10. Photo replaces existing gate code image
11. Coordinates updated at community level

### Admin Approval Flow
1. Admin logs into admin panel
2. Views pending contributions
3. Reviews submission details in modal
4. Approves or rejects
5. On approve:
   - New contributions: Added to gates.json
   - Photo updates: Existing code updated
   - Files moved from temp to assets
   - Suggestions removed
6. Changes immediately reflected on public site

---

## 🚀 Performance Optimizations

- **JSON Caching**: `cache: 'no-store'` for fresh data
- **Image Optimization**: Compression reduces bandwidth
- **Lazy Loading**: Data loaded on demand
- **Efficient Search**: Normalized string comparison
- **Minimal Dependencies**:
  - EXIF.js for GPS extraction
  - Native browser APIs
  - No heavy frameworks

---

## 📧 Email System

### PHPMailer Integration
- **Email Recovery**: PIN recovery via email
- **SMTP Configuration** (email_config.php):
  - Gmail, Outlook, or custom SMTP support
  - TLS/SSL encryption
  - App Password support for Gmail
  - Configurable FROM address
- **Email Template**:
  - Professional HTML design
  - Responsive email layout
  - PIN display in monospace box
  - Security notice included
  - Branded footer
- **Installation**:
  - Composer required: `composer require phpmailer/phpmailer`
  - See INSTALL_PHPMAILER.md for setup guide
  - SMTP credentials configured in email_config.php
- **Email Functions**:
  - send_email($to, $subject, $body, $altBody)
  - get_pin_recovery_template($name, $pin)
  - Automatic fallback to plain text

---

## 🔒 System Requirements

- **Server**: Apache/Nginx with PHP 7.4+
- **PHP Extensions**:
  - `exif` for GPS extraction
  - `gd` or `imagick` for image processing
  - `json` for data handling
  - `session` for authentication
  - `mbstring` for string operations
  - `openssl` for SMTP/TLS (email)
- **Composer**: Required for PHPMailer installation
- **Browser Support**:
  - Modern browsers (Chrome, Firefox, Safari, Edge)
  - Geolocation API support
  - File API support
  - LocalStorage support
  - Clipboard API (for PIN copy)
- **Permissions**:
  - Write access to `data/`, `assets/`, `temp_assets/`
  - PHP session directory writable
  - Composer vendor directory writable
- **Email** (Optional):
  - SMTP server credentials
  - PHPMailer via Composer
  - Gmail App Password or SMTP auth

---

## 📋 Version Control

- **Version Display**: Shown in footer of all pages
- **Build Tracking**: Defined in `admin/includes/config.php`
- **Update History**: Managed through this documentation

---

## 🎉 Notable UI Improvements

- **Silent Code Deletion**: No alert modal when deleting codes in admin
- **Proper Code Numbering**: Renumbers correctly after deletions
- **Warning Icons**: Centered and properly spaced in Edit modal
- **Scrollable Modals**: Content doesn't overflow on mobile
- **Fixed Backgrounds**: Gradient backgrounds don't repeat or cut off
- **Responsive Form Layouts**: Important field pairs stay side-by-side
- **Modal Stacking**: User info and theme toggle behind modals (z-index: 10)
- **Pending Photo Check**: Prevents duplicate photo update submissions
- **All Modals Close**: Success alert closes all open modals
- **Gallery Image Management**: Complete CRUD with auto-update of gates.json references
- **Admin Badge**: Visual indicator in Settings page header
- **Live Time Display**: Current server time updates every second in Settings
- **Radio Button Styling**: Custom-styled radio options with live format examples
- **Role Badges**: Color-coded Admin/User badges with gradients
- **PIN Click-to-Copy**: Shows "Copied" for 2 seconds instead of modal
- **Logout Button**: Red logout button at bottom of sidebar with divider
- **Login Page Design**: Modern gradient design with password masking
- **Email Recovery UI**: Clean forgot PIN page with security notices
- **User Form Enhancement**: Add/Edit forms now include email and role fields

---

## 🔧 Future Enhancement Possibilities

- ~~Email notifications for contributions~~ ✅ Implemented (PIN recovery)
- Multi-language support
- Advanced search filters
- Community favorites/bookmarks
- QR code generation for gate codes
- API rate limiting
- Two-factor authentication
- Community voting system
- Photo galleries per community
- Map view of all communities
- Export to mobile apps
- SMTP test functionality in Settings
- Email templates for admin notifications
- Password reset via email

---

**Current Version**: 1.1.6
**Last Updated**: January 2025
**System**: Gate Code Management Platform

---

## 📝 Recent Changes (v1.1.6)

### Authentication System Overhaul
- ✅ Implemented role-based authentication (User/Admin)
- ✅ Created admin login page with PIN authentication
- ✅ Added "Forgot PIN?" email recovery system
- ✅ PHPMailer integration for email delivery
- ✅ Beautiful HTML email template for PIN recovery
- ✅ Session-based authentication (replaces ?key= dependency)
- ✅ Logout functionality with session cleanup
- ✅ Backward compatibility with ?key= parameter

### User Management Enhancement
- ✅ Added email field to user registration
- ✅ Added role dropdown (User/Admin)
- ✅ Role badges with color coding
- ✅ Email validation and uniqueness check
- ✅ PIN click-to-copy (shows "Copied" text)
- ✅ Enhanced search (name, email, role, PIN)
- ✅ User form redesign with all new fields

### Security Improvements
- ✅ Role-based access control (RBAC)
- ✅ Email validation (FILTER_VALIDATE_EMAIL)
- ✅ Unique email constraint
- ✅ Session hijacking prevention
- ✅ Secure password masking in forms
- ✅ SMTP/TLS encryption support

### UI/UX Enhancements
- ✅ Modern login page design
- ✅ Forgot PIN recovery page
- ✅ Logout button in sidebar (red color)
- ✅ Admin badge in Settings page
- ✅ Role badges (Admin/User) with gradients
- ✅ PIN copy feedback improvement

### Documentation
- ✅ Created INSTALL_PHPMAILER.md guide
- ✅ Updated UPDATE.md with new features
- ✅ Composer.json for dependencies
- ✅ Email configuration documentation
