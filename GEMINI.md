# Project Overview

This project is a basic PHP web application for the Department of Agriculture (DA), connecting farmers and buyers. It uses Bootstrap for its front-end framework and custom CSS for styling.

# Building and Running

To run this project locally, use PHP's built-in web server:
1.  Navigate to the project's root directory.
2.  Run: `php -S localhost:8000`
3.  Open `http://localhost:8000` in your browser.

# Development Conventions

*   **Languages:** PHP, HTML, CSS, JavaScript
*   **Styling:** Bootstrap 5 and custom CSS in `css/`.
*   **Structure:** Role-based modules (`da/`, `farmer/`, `buyer/`) with shared components in `header/`, `footer/`, and `includes/`.

# Recent Progress

### March 4, 2026
*   **Gantt Chart Removal:** Removed Gantt chart modules and navigation links from Farmer, Buyer, and DA sections to streamline the application.
*   **Header & Sidebar Fixes:** 
    *   Fixed hamburger menu functionality in the profile page by ensuring scripts load correctly after the DOM is ready.
    *   Improved brand visibility by making "DFPS" titles visible on all screen sizes.
    *   Standardized header links using relative paths for better reliability across different subdirectories.
*   **Profile Picture Enhancements:**
    *   Integrated Bootstrap Icons (`bi-person-circle`) as default placeholders for profile pictures.
    *   Implemented profile picture display in the messaging interface for both Farmers and Buyers.
    *   Added a "Remove Profile Picture" feature in the profile settings, allowing users to revert to the default icon and delete the uploaded file.
    *   Updated the DA User Management list to show profile pictures or icons.
*   **Messaging UI Improvements:** 
    *   Modernized the messaging interface by displaying participant avatars/icons in the chat list, header, and message bubbles.
    *   Ensured consistent styling and layout across different user roles.
*   **Universal Header & Footer System:**
    *   Created `includes/universal_header.php` and `includes/universal_footer.php` to eliminate massive code duplication across role-based modules.
    *   The new system dynamically handles menu items, brand titles, primary colors, and asset paths (`base_path` calculation) based on the logged-in user's role.
    *   Refactored all existing role-specific headers (`header/headerfarmer.php`, etc.) to act as simple wrappers for the universal components, making the system significantly easier to troubleshoot and update.
*   **Duplicate UI Cleanup:** 
    *   Removed redundant "Login" link from the Guest sidebar/hamburger menu by centralizing authentication links in the universal header logic.
