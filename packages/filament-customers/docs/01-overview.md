---
title: Overview
---

# Filament Customers Plugin

The Filament Customers plugin provides a complete admin panel interface for managing customers in the AIArmada Commerce ecosystem. It includes resources for customers and segments, along with dashboard widgets for customer analytics.

## Features

### Customer Resource
- **Full CRUD**: Create, read, update, and delete customer records
- **Rich Forms**: Comprehensive forms with validation
- **Advanced Filters**: Filter by status, segments, marketing preferences
- **Bulk Actions**: Mass operations on multiple customers
- **Infolist Views**: Detailed customer information display
- **Global Search**: Quick customer lookup across the admin panel

### Segment Resource
- **Segment Management**: Create and manage customer segments
- **Automatic/Manual**: Support for both rule-based and manual segments
- **Condition Builder**: Visual interface for segment rules
- **Rebuild Actions**: One-click segment rebuilding
- **Member Preview**: See segment members before saving

### Relation Managers
- **Addresses**: Manage customer addresses inline
- **Notes**: Add internal and customer-visible notes

### Widgets
- **Customer Stats**: Real-time customer metrics and trends
- **Recent Customers**: Display of recently registered customers

### Owner Scoping
- **Automatic Filtering**: All queries respect owner boundaries
- **No UI Trust**: Server-side validation of all operations
- **Consistent Scoping**: Owner context applied across all resources

## Integration

Works seamlessly with:
- **Filament v5**: Built for the latest Filament version
- **Customers Package**: Depends on aiarmada/customers core package
- **Multi-Tenancy**: Full owner scoping support
- **Authentication**: Respects Laravel policies
- **Other Filament Plugins**: Integrates with other commerce Filament packages

## Screenshots

### Customer List
Filter, search, and manage customers with inline actions.

### Customer View
Comprehensive customer profile with customer details and recent activity.

### Segment Builder
Visual interface for creating automatic segments with rule conditions.

### Dashboard Widgets
Real-time customer metrics and recent customer list.

## Architecture

The plugin follows Filament best practices:
- **Resources**: CustomerResource, SegmentResource
- **Pages**: List, Create, Edit, View pages for each resource
- **Relation Managers**: Nested relationships on customer record
- **Widgets**: Dashboard statistics and insights
- **Owner Scoping**: Centralized owner query logic
- **Policies**: Authorization via Laravel policies

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5+
- aiarmada/customers package

## Next Steps

- [Installation](02-installation.md) - Set up the plugin
- [Resources](04-resources.md) - Learn about resources
- [Widgets](05-widgets.md) - Dashboard widgets guide
