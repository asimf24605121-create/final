# ClearOrbit — Group Buy SaaS Platform

## Overview
ClearOrbit is a secure, premium Shared Access Management SaaS platform designed for a Group Buy business model. It facilitates the management of shared platform credentials (cookies) and grants time-limited access to users. The platform includes a Chrome Extension for secure cookie injection, heartbeat monitoring, and anti-abuse mechanisms. The project aims to provide a robust, scalable, and user-friendly solution for managing shared access to various online platforms, focusing on security, performance, and an intuitive user experience.

## User Preferences
I prefer clear, concise language.
I prefer an iterative development approach with frequent, small updates.
Please ask for confirmation before making significant architectural changes or adding new external dependencies.
I prefer a detailed explanation of the code changes.
Please ensure all solutions are cross-browser compatible where applicable.

## System Architecture
The application features a plain PHP 8.2 backend with PDO for database interactions and PHP sessions for state management. The frontend is built with HTML5, Vanilla JavaScript, and Tailwind CSS v3 (CDN), adhering to a clean, professional, white/light theme. The Chrome Extension operates on Manifest V3, utilizing a content script for dashboard interaction and a service worker for core functionalities like cookie injection, heartbeat monitoring, and anti-abuse features.

Key architectural and feature specifications include:
-   **UI/UX**: Clean white/light theme inspired by Stripe/Notion using Tailwind CSS, applied consistently across all UIs (login, admin, user dashboard, public buy page, user profile).
-   **Authentication & Authorization**: Implements CSRF protection, rate limiting for login, and an RBAC system with `super_admin` and `manager` roles. Features multi-device login limits and browser fingerprinting for device locking.
-   **Multi-Account Slot System**: A dynamic system allowing unlimited "Login Slots" per platform with configurable max users. Users are automatically assigned to an available slot upon "Access Now" click. Performance is optimized with transaction-based slot assignment and row locking to prevent race conditions. Includes 5-minute active window for user counting and 10-minute stale session auto-cleanup.
-   **Smart Slot Scoring**: An AI-based scoring mechanism prioritizes healthy and high-performing slots using a formula `(success_count * 2) - (fail_count * 3)`. Slots are ordered by health status, then score, then last successful use. Auto-degrades slot health based on fail rates.
-   **Cooldown System**: Failed slots enter a 10-minute cooldown period, during which they are excluded from selection.
-   **Real Login Verification**: The Chrome Extension performs DOM-based login verification post-cookie injection using platform-specific CSS selectors and URL patterns before reporting success/failure to the backend.
-   **Strict Session Protection**: Enforces a "1 user = 1 active session per platform" policy, returning the same slot if an active session exists.
-   **Cookie Management**: Cookies are base64_encoded for storage, with platform auto-detection from cookie domains and secure injection via the Chrome extension, including temporary expiry and heartbeat monitoring.
-   **Subscription & User Management**: Admin functionalities for adding/managing users, assigning bulk subscriptions, and extending existing ones. User profiles display active subscriptions.
-   **Platform Management**: Admins can add, activate, and deactivate platforms, which dynamically update user access.
-   **Activity Logging**: Comprehensive tracking of significant user and admin actions.
-   **Password Reset**: Token-based system with email delivery via PHPMailer.
-   **Support Tickets**: Users can report issues, and admins can manage tickets.
-   **Announcements**: A time-based, multi-type announcement system (Popup/Notification) with scheduling and CRUD functionalities for super admins.
-   **Contact Messages**: A public contact form with admin review capabilities.
-   **Extension-Dashboard Communication**: Secure `window.postMessage` for communication between dashboard and content script, relayed to the service worker via `chrome.runtime.sendMessage`. Server-provided `redirect_url` and `platform_id` are central for routing.
-   **Global Logout Sync**: Cross-tab logout synchronization via `localStorage` events, ensuring consistent state across all open tabs and clearing injected cookies.
-   **Persistent Notifications**: A Facebook/LinkedIn-style notification system with a bell icon, unread count, dropdown panel, and "mark all as read" functionality, updated via polling.
-   **Database Schema**: Core tables include `users`, `platforms`, `cookie_vault`, `platform_accounts`, `account_sessions`, `user_subscriptions`, `activity_logs`, `login_attempts`, `pricing_plans`, `whatsapp_config`, `payments`, `password_reset_tokens`, `support_tickets`, `announcements`, `contact_messages`, `user_sessions`, `login_attempt_logs`, and `user_notifications`.
-   **Security**: Employs `SameSite` and `HttpOnly` cookie flags, CORS configuration, session management with device-type enforcement, inactivity timeouts, and session validation.
-   **Admin Security Panel**: Allows super admins to monitor active sessions, detect suspicious activity, and manage login attempt logs.
-   **User Profile System**: Mandatory profile completion before platform access, including user-editable fields and profile image uploads.
-   **Strict RBAC (Manager Role)**: Managers have restricted access, primarily to user management, with all other sensitive admin functionalities locked to `super_admin`.
-   **Admin Overview Control Center**: A redesigned overview page with a sticky system health bar, KPI cards with trend hints, a predictive alert system, platform load engine, slot intelligence panel, live event stream, and quick action buttons, all driven by real-time database metrics.
-   **Admin Add User (Minimal)**: Simplified user creation process requiring only username, email, and password, with profile completion deferred to the user.
-   **Mobile Responsive Design**: Implemented using `responsive.css` with shared mobile components, slide-in navigation drawers, 44px minimum touch targets, and adaptable layouts for various screen sizes across all key pages.

## External Dependencies
-   **Database**: SQLite (development), MySQL 8.0+ (production)
-   **CSS Framework**: Tailwind CSS v3 (CDN)
-   **Email**: PHPMailer (for SMTP, with fallback to PHP's `mail()` function)
-   **Chrome APIs**: `chrome.cookies`, `chrome.storage`, `chrome.tabs`, `chrome.alarms` (used by the Chrome Extension)
-   **intl-tel-input**: v18 (CDN) — International phone input with country flag selector and validation on profile edit form
-   **Profile Tab System**: Hash-based tab switching (`profile.html#view`, `#edit`, `#password`) with `history.replaceState` and `hashchange` listener
-   **Admin Overview**: KPI card tooltips, "Last updated Xs ago" indicator in status bar, platform load zero-slot handling, slot intelligence "No activity yet" fallback
-   **Dashboard Profile Dropdown**: Avatar circle with `toggleProfileDropdown()`, click-outside close, links to profile/password/signout