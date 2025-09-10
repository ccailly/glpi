# UserDevicesConditionHandler Enhanced Configuration

This document explains the enhanced configuration options for the `UserDevicesConditionHandler` that provide more flexible operator selection based on various parameters.

## Overview

The refactored `UserDevicesConditionHandler` now supports context-aware operator selection through the `UserDevicesConditionHandlerConfig` class. This allows for more demanding use cases where the handler should return different value operators depending on specific parameters.

## Backward Compatibility

The handler maintains full backward compatibility. Existing code using the boolean parameter will continue to work unchanged:

```php
// This still works exactly as before
$handler = new UserDevicesConditionHandler(false); // Single device
$handler = new UserDevicesConditionHandler(true);  // Multiple devices
```

## Enhanced Configuration

### Context-Based Configuration

Use predefined contexts to get different sets of operators:

```php
use Glpi\Form\Condition\ConditionHandler\UserDevicesConditionHandlerConfig;

// Flexible single device - includes equals/not_equals operators
$config = UserDevicesConditionHandlerConfig::withContext(false, 'flexible_single');
$handler = new UserDevicesConditionHandler($config);
// Operators: is_itemtype, is_not_itemtype, equals, not_equals

// Flexible multiple devices - includes contains/not_contains operators  
$config = UserDevicesConditionHandlerConfig::withContext(true, 'flexible_multiple');
$handler = new UserDevicesConditionHandler($config);
// Operators: at_least_one_item_of_itemtype, all_items_of_itemtype, contains, not_contains

// Strict contexts for limited operator sets
$config = UserDevicesConditionHandlerConfig::withContext(false, 'strict_single');
// Operators: is_itemtype only

$config = UserDevicesConditionHandlerConfig::withContext(true, 'strict_multiple');  
// Operators: all_items_of_itemtype only
```

### Parameter-Based Configuration

Configure handlers based on user roles or permissions:

```php
// Admin users get additional operators
$config = new UserDevicesConditionHandlerConfig(
    is_multiple_devices: false,
    additional_parameters: ['user_role' => 'admin']
);
$handler = new UserDevicesConditionHandler($config);
// Operators: is_itemtype, is_not_itemtype, empty, not_empty

// Users with advanced permissions get extended operators
$config = new UserDevicesConditionHandlerConfig(
    is_multiple_devices: false,
    additional_parameters: ['permissions' => ['advanced_conditions']]
);
$handler = new UserDevicesConditionHandler($config);
// Operators: is_itemtype, is_not_itemtype, greater_than, less_than, match_regex

// Regular users get limited operators
$config = new UserDevicesConditionHandlerConfig(
    is_multiple_devices: true,
    additional_parameters: ['user_role' => 'user']
);
$handler = new UserDevicesConditionHandler($config);
// Operators: at_least_one_item_of_itemtype only
```

### Device Type Specific Configuration

Configure handlers for specific device types:

```php
// Single device type - simplified operators
$config = UserDevicesConditionHandlerConfig::withDeviceTypes(false, ['Computer']);
$handler = new UserDevicesConditionHandler($config);
// Operators: equals, not_equals

// Multiple device types - standard operators
$config = UserDevicesConditionHandlerConfig::withDeviceTypes(true, ['Computer', 'Printer']);
$handler = new UserDevicesConditionHandler($config);
// Operators: at_least_one_item_of_itemtype, all_items_of_itemtype
```

## Usage in QuestionTypes

The `QuestionTypeUserDevice` class provides an enhanced method for getting condition handlers:

```php
// Standard usage (backward compatible)
$handlers = $questionType->getConditionHandlers($config);

// Enhanced usage with context
$handlers = $questionType->getEnhancedConditionHandlers(
    $config, 
    'flexible_single',
    ['user_role' => 'admin']
);
```

## Available Contexts

- `flexible_single`: Adds equals/not_equals operators for single devices
- `flexible_multiple`: Adds contains/not_contains operators for multiple devices  
- `strict_single`: Only is_itemtype operator
- `strict_multiple`: Only all_items_of_itemtype operator

## Available Additional Parameters

- `user_role`: 'admin' | 'user' - Affects available operators based on user role
- `permissions`: array - List of permissions that unlock additional operators
- Custom parameters can be added by extending the configuration logic

## Extended Operators

The enhanced configuration enables these additional operators:

- `EQUALS` / `NOT_EQUALS`: Direct value comparison
- `CONTAINS` / `NOT_CONTAINS`: Array containment checks
- `EMPTY` / `NOT_EMPTY`: Empty value checks
- `GREATER_THAN` / `LESS_THAN`: Numeric comparisons (with advanced permissions)
- `MATCH_REGEX`: Regular expression matching (with advanced permissions)

## Migration Guide

Existing code requires no changes. To take advantage of enhanced features:

1. Replace boolean parameters with `UserDevicesConditionHandlerConfig` objects
2. Use appropriate factory methods (`withContext`, `withDeviceTypes`) or constructor
3. Define contexts and parameters based on your use case requirements
4. Update condition evaluation logic to handle new operators if needed

## Examples

### Example 1: Role-based operator selection

```php
$userRole = Session::getCurrentUserRole();
$config = new UserDevicesConditionHandlerConfig(
    is_multiple_devices: false,
    additional_parameters: ['user_role' => $userRole]
);
$handler = new UserDevicesConditionHandler($config);
```

### Example 2: Permission-based configuration

```php
$permissions = Session::getCurrentUserPermissions();
$config = new UserDevicesConditionHandlerConfig(
    is_multiple_devices: true,
    additional_parameters: ['permissions' => $permissions]
);
$handler = new UserDevicesConditionHandler($config);
```

### Example 3: Context-aware form building

```php
$context = $isAdvancedForm ? 'flexible_multiple' : 'strict_multiple';
$config = UserDevicesConditionHandlerConfig::withContext(true, $context);
$handler = new UserDevicesConditionHandler($config);
```