# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2025-08-15

### Added
- **PSR-14 Event-Driven Architecture**: Complete refactor to use PSR-14 compliant event system
  - `UserRegisteredEvent` dispatched after successful user registration
  - `SendVerificationEmailHandler` for handling verification emails via events
  - `SimpleEventDispatcher` and `SimpleListenerProvider` for framework-agnostic event handling
  - Comprehensive event-driven architecture documentation
- **Enhanced Test Coverage**: Achieved 100% test coverage for all event system components
  - `SimpleEventDispatcher`: 100% coverage (7/7 lines, 2/2 methods)
  - `SimpleListenerProvider`: 100% coverage (3/3 lines, 2/2 methods)  
  - `UserRegisteredEvent`: 100% coverage (7/7 lines, 7/7 methods)
  - `SendVerificationEmailHandler`: 100% coverage (28/28 lines, 2/2 methods)
  - `AuthorizationServerFactory`: 100% coverage (48/48 lines, 1/1 method)
- **Comprehensive Documentation**:
  - Event-driven architecture guide with framework integration examples
  - Expo deep link setup guide for React Native applications
  - Magic link PKCE integration documentation
  - OAuth2 endpoints documentation
- **Database Improvements**:
  - Enhanced PostgreSQL migrations with proper up/down methods
  - Added foreign key constraints and indexes for better data integrity
  - OAuth refresh tokens table creation
  - Improved migration structure and error handling

### Changed
- **BREAKING CHANGE**: `RegisterUser` constructor signature changed
  - Now requires `EventDispatcherInterface` instead of `MagicLinkTokenFactory` and `SendVerificationLinkInterface`
  - Email sending moved to event handlers for better separation of concerns
- **Enhanced Error Handling**: Email sending failures no longer break user registration process
- **Improved Code Quality**:
  - PHP CS Fixer integration with PSR-12 compliance
  - PHPStan static analysis improvements
  - Comprehensive test coverage across all components
- **Database Schema Updates**:
  - Updated migrations for better PostgreSQL compatibility
  - Added proper namespaces to PostgreSQL migration classes
  - Improved timezone handling in database operations

### Fixed
- **GitHub CI Issues**: 
  - Fixed private key handling in tests using embedded test keys
  - Resolved UOPZ installation issues in GitHub Actions
  - Fixed CS Fixer violations across all test files
- **Database Compatibility**:
  - Fixed PostgreSQL timezone handling in RefreshTokenRepository
  - Improved UserRepository handling of additional_data field
  - Resolved migration issues with proper up/down methods
- **Test Reliability**:
  - Fixed integration tests with proper dependency injection
  - Resolved test failures related to API changes
  - Improved test isolation and cleanup

### Security
- **Enhanced PKCE Support**: Improved PKCE implementation with better security practices
- **Secure Token Handling**: Better validation and error handling for magic link tokens
- **CSRF Protection**: State parameter validation in OAuth flows

### Dependencies
- **Added**: `psr/event-dispatcher ^1.0` for PSR-14 event handling
- **Updated**: Various dependency version constraints for better compatibility
- **Development**: Added `friendsofphp/php-cs-fixer` for code style enforcement

### Migration Guide
For users upgrading from v0.2.x:

1. **Update RegisterUser Usage**:
   ```php
   // Before
   new RegisterUser($clientRepo, $tokenFactory, $emailSender, $hook, $userRepo)
   
   // After  
   new RegisterUser($clientRepo, $eventDispatcher, $hook, $userRepo)
   ```

2. **Configure Event System**: Update your dependency injection to include event dispatcher and handlers

3. **Database Migrations**: Run new migrations for OAuth refresh tokens and schema improvements

### Statistics
- **Total Tests**: 127 (up from 84)
- **Total Assertions**: 504 (up from 333)
- **Code Coverage**: 100% on all event system components
- **New Files**: 8 new classes and comprehensive documentation

---

## [0.2.x] - Previous Releases

Previous release notes can be found in the git history.
