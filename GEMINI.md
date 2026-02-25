# Project Overview

This project appears to be a basic PHP web application. It uses Bootstrap for its front-end framework, including Bootstrap CSS and Bootstrap Icons (via CDN). Custom styling is provided by `css/style.css`. The application structure includes `index.php` as the main page, and `header.php` for common header content and asset includes. The `footer.php` file is currently empty.

# Building and Running

PHP projects typically don't have a "build" step. They are served directly by a web server with PHP support.

To run this project locally, you would need a web server with PHP installed (e.g., Apache, Nginx, or PHP's built-in web server).

**Using PHP's built-in web server:**
1.  Navigate to the project's root directory in your terminal.
2.  Run the command: `php -S localhost:8000`
3.  Open your web browser and go to `http://localhost:8000`

# Development Conventions

*   **Languages:** PHP, HTML, CSS
*   **Styling:** Bootstrap (version implied by `bootstrap.css` filename and CDN link for Bootstrap Icons 1.11.1) and custom CSS in `css/style.css`.
*   **Structure:** Uses `header.php` and `footer.php` for common page elements.
