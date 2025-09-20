# TetherPHP Core

TetherPHP Core provides the foundational framework files and essential components for building applications with the TetherPHP ecosystem. It is designed to be lightweight, modular, and extensible, serving as the backbone for TetherPHP projects.

## Features

- **Routing**: Flexible and simple routing system for HTTP requests.
- **Request/Response Handling**: Abstractions for HTTP requests and responses.
- **Session Management**: Secure session handling with support for CSRF protection and session expiration.
- **CSRF Protection**: Built-in CSRF token generation and validation.
- **Command System**: Console command framework for CLI tooling and automation.
- **View Rendering**: Basic view interface for rendering templates and error pages.
- **Helpers & Traits**: Utility functions and traits to speed up development.

## Directory Structure

- `src/`
  - `Kernel.php` — Application kernel and bootstrap logic.
  - `Router.php` — Routing logic and route registration.
  - `framework/`
    - `Commands/` — Console command classes.
    - `DTOs/` — Data transfer objects for internal use.
    - `Helpers/` — Global helper functions.
    - `Interfaces/` — Core framework interfaces.
    - `Modules/` — Core modules (logging, environment, etc.).
    - `Requests/` — HTTP request abstraction and logic.
    - `Sessions/` — Session and CSRF management.
    - `Templates/` — Stubs for code generation.
    - `Traits/` — Commonly used traits.
    - `Views/` — View and error templates.

## Contributing

Contributions are welcome! Please open issues or submit pull requests for bug fixes, improvements, or new features.

## License

This project is open-sourced under the MIT license. See the [LICENSE.md](LICENSE.md) file for details.
