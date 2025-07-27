# LinkShortener API Documentation

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Rate Limiting](#rate-limiting)
4. [Error Handling](#error-handling)
5. [Partner Management](#partner-management)
6. [Link Shortening](#link-shortening)
7. [Analytics](#analytics)
8. [Webhooks](#webhooks)
9. [SDKs](#sdks)
10. [Examples](#examples)

## Overview

The LinkShortener API provides a comprehensive solution for URL shortening with partner integration, revenue sharing, and advanced analytics. This RESTful API allows third-party applications to integrate link shortening functionality while generating revenue through advertisement displays.

### Base URL

```
Production: https://api.linkshortener.com
Staging: https://api-staging.linkshortener.com
```

### API Version

Current version: `v1`

All API endpoints are prefixed with `/api/v1/`

### Content Type

All requests and responses use `application/json` content type unless otherwise specified.

## Authentication

The API supports multiple authentication methods:

### 1. API Key Authentication

Include your API key in the request headers:

```http
Authorization: Bearer YOUR_API_KEY
```

### 2. OAuth 2.0

For more secure access, use OAuth 2.0 with the following endpoints:

- **Authorization URL**: `/oauth/authorize`
- **Token URL**: `/oauth/token`
- **Token Info**: `/oauth/token/info`

#### OAuth Flow Example

```http
# Step 1: Get authorization code
GET /oauth/authorize?response_type=code&client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&scope=links:create,analytics:read

# Step 2: Exchange code for access token
POST /oauth/token
Content-Type: application/json

{
  "grant_type": "authorization_code",
  "client_id": "YOUR_CLIENT_ID",
  "client_secret": "YOUR_CLIENT_SECRET",
  "code": "AUTHORIZATION_CODE",
  "redirect_uri": "YOUR_REDIRECT_URI"
}
```

### 3. Session-based Authentication

For web applications, use session-based authentication after login.

## Rate Limiting

API requests are rate-limited based on your subscription plan:

- **Free Plan**: 1,000 requests/hour
- **Starter Plan**: 10,000 requests/hour  
- **Professional Plan**: 100,000 requests/hour
- **Enterprise Plan**: 1,000,000 requests/hour

Rate limit headers are included in all responses:

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

## Error Handling

The API uses standard HTTP status codes and returns detailed error information:

### Error Response Format

```json
{
  "error": {
    "code": "INVALID_REQUEST",
    "message": "The request is invalid",
    "details": {
      "field": "url",
      "issue": "URL is required"
    }
  },
  "timestamp": "2023-01-01T00:00:00Z",
  "request_id": "req_1234567890"
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_REQUEST` | 400 | Request validation failed |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `RATE_LIMITED` | 429 | Rate limit exceeded |
| `SERVER_ERROR` | 500 | Internal server error |

## Partner Management

### Register New Partner

Create a new partner account with subscription plan selection.

```http
POST /api/v1/partners/register
Content-Type: application/json

{
  "company_name": "Tech Corp",
  "email": "admin@techcorp.com",
  "password": "SecurePassword123!",
  "phone": "+1234567890",
  "plan_id": "starter",
  "billing_address": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "zip": "10001",
    "country": "US"
  },
  "business_type": "company"
}
```

**Response:**

```json
{
  "partner": {
    "id": "partner_1234567890",
    "company_name": "Tech Corp",
    "email": "admin@techcorp.com",
    "status": "pending",
    "plan_id": "starter",
    "created_at": "2023-01-01T00:00:00Z"
  },
  "verification_required": true,
  "next_steps": [
    "Check your email for verification link",
    "Complete payment setup",
    "Set up API integration"
  ]
}
```

### Partner Login

Authenticate and create a session.

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@techcorp.com",
  "password": "SecurePassword123!",
  "mfa_code": "123456"
}
```

**Response:**

```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "partner": {
    "id": "partner_1234567890",
    "company_name": "Tech Corp",
    "email": "admin@techcorp.com",
    "status": "active",
    "plan_id": "starter"
  }
}
```

### Get Partner Profile

Retrieve current partner information.

```http
GET /api/v1/partners/me
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Response:**

```json
{
  "partner": {
    "id": "partner_1234567890",
    "company_name": "Tech Corp",
    "email": "admin@techcorp.com",
    "phone": "+1234567890",
    "status": "active",
    "plan_id": "starter",
    "email_verified": true,
    "phone_verified": false,
    "mfa_enabled": true,
    "api_key": "ls_1234567890abcdef",
    "revenue_share": 0.50,
    "created_at": "2023-01-01T00:00:00Z"
  },
  "profile": {
    "first_name": "John",
    "last_name": "Doe",
    "company_website": "https://techcorp.com",
    "business_type": "company",
    "kyc_status": "verified",
    "completion_percentage": 85
  },
  "subscription": {
    "plan_name": "Starter Plan",
    "api_requests_limit": 10000,
    "current_usage": 1250,
    "usage_percentage": 12.5,
    "billing_cycle": "monthly",
    "next_billing_date": "2023-02-01T00:00:00Z"
  }
}
```

### Update Partner Profile

Update partner profile information.

```http
PUT /api/v1/partners/me/profile
Authorization: Bearer YOUR_ACCESS_TOKEN
Content-Type: application/json

{
  "first_name": "John",
  "last_name": "Doe",
  "company_website": "https://techcorp.com",
  "company_description": "Leading technology solutions provider",
  "timezone": "America/New_York",
  "notification_preferences": {
    "email_reports": true,
    "sms_alerts": false,
    "webhook_notifications": true
  }
}
```

## Link Shortening

### Create Short Link

Generate a shortened URL with optional customization.

```http
POST /api/v1/shorten
Authorization: Bearer YOUR_ACCESS_TOKEN
Content-Type: application/json

{
  "url": "https://example.com/very/long/url/with/parameters?param1=value1&param2=value2",
  "custom_alias": "my-link",
  "title": "Example Page",
  "description": "This is an example page for demonstration",
  "expires_at": "2023-12-31T23:59:59Z",
  "metadata": {
    "campaign": "summer2023",
    "source": "email"
  }
}
```

**Response:**

```json
{
  "short_url": "https://linkshortener.com/abc123",
  "short_code": "abc123",
  "original_url": "https://example.com/very/long/url/with/parameters?param1=value1&param2=value2",
  "title": "Example Page",
  "description": "This is an example page for demonstration",
  "custom_alias": "my-link",
  "expires_at": "2023-12-31T23:59:59Z",
  "created_at": "2023-01-01T00:00:00Z",
  "click_count": 0,
  "status": "active",
  "qr_code": "https://linkshortener.com/qr/abc123.png"
}
```

### Bulk Link Creation

Create multiple short links in a single request.

```http
POST /api/v1/shorten/bulk
Authorization: Bearer YOUR_ACCESS_TOKEN
Content-Type: application/json

{
  "links": [
    {
      "url": "https://example.com/page1",
      "title": "Page 1"
    },
    {
      "url": "https://example.com/page2",
      "title": "Page 2",
      "custom_alias": "page2"
    }
  ]
}
```

**Response:**

```json
{
  "results": [
    {
      "success": true,
      "short_url": "https://linkshortener.com/def456",
      "short_code": "def456",
      "original_url": "https://example.com/page1",
      "title": "Page 1"
    },
    {
      "success": true,
      "short_url": "https://linkshortener.com/page2",
      "short_code": "page2",
      "original_url": "https://example.com/page2",
      "title": "Page 2"
    }
  ],
  "summary": {
    "total": 2,
    "successful": 2,
    "failed": 0
  }
}
```

### Get Link Details

Retrieve information about a specific short link.

```http
GET /api/v1/links/abc123
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Response:**

```json
{
  "link": {
    "id": 12345,
    "short_code": "abc123",
    "short_url": "https://linkshortener.com/abc123",
    "original_url": "https://example.com/very/long/url",
    "title": "Example Page",
    "description": "This is an example page",
    "custom_alias": "my-link",
    "partner_id": "partner_1234567890",
    "created_at": "2023-01-01T00:00:00Z",
    "updated_at": "2023-01-01T00:00:00Z",
    "expires_at": "2023-12-31T23:59:59Z",
    "click_count": 42,
    "status": "active",
    "metadata": {
      "campaign": "summer2023",
      "source": "email"
    }
  },
  "analytics": {
    "total_clicks": 42,
    "unique_visitors": 38,
    "click_through_rate": 8.5,
    "top_countries": ["US", "UK", "CA"],
    "top_devices": ["desktop", "mobile", "tablet"],
    "recent_clicks": 12
  }
}
```

### List Partner Links

Get a paginated list of links for the authenticated partner.

```http
GET /api/v1/links?page=1&limit=50&status=active&sort=created_at&order=desc
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Response:**

```json
{
  "links": [
    {
      "short_code": "abc123",
      "short_url": "https://linkshortener.com/abc123",
      "original_url": "https://example.com/page1",
      "title": "Example Page 1",
      "click_count": 42,
      "status": "active",
      "created_at": "2023-01-01T00:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 150,
    "total_pages": 3,
    "has_next": true,
    "has_prev": false
  }
}
```

### Update Link

Modify an existing short link.

```http
PUT /api/v1/links/abc123
Authorization: Bearer YOUR_ACCESS_TOKEN
Content-Type: application/json

{
  "title": "Updated Title",
  "description": "Updated description",
  "expires_at": "2024-01-31T23:59:59Z",
  "status": "active"
}
```

### Delete Link

Disable or delete a short link.

```http
DELETE /api/v1/links/abc123
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Response:**

```json
{
  "message": "Link successfully deleted",
  "short_code": "abc123",
  "deleted_at": "2023-01-01T12:00:00Z"
}
```

## Analytics

### Get Partner Analytics

Retrieve comprehensive analytics for the partner.

```http
GET /api/v1/analytics?period=30d&timezone=America/New_York
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Response:**

```json
{
  "summary": {
    "total_links": 150,
    "total_clicks": 5420,
    "unique_visitors": 4890,
    "click_through_rate": 8.2,
    "revenue_generated": 125.50,
    "partner_earnings": 62.75
  },
  "trends": {
    "clicks_by_day": [
      {"date": "2023-01-01", "clicks": 45, "unique_visitors": 42},
      {"date": "2023-01-02", "clicks": 52, "unique_visitors": 48}
    ],
    "revenue_by_day": [
      {"date": "2023-01-01", "revenue": 2.25, "partner_earnings": 1.125},
      {"date": "2023-01-02", "revenue": 2.60, "partner_earnings": 1.30}
    ]
  },
  "demographics": {
    "countries": [
      {"country": "US", "clicks": 2100, "percentage": 38.7},
      {"country": "UK", "clicks": 890, "percentage": 16.4}
    ],
    "devices": [
      {"device": "desktop", "clicks": 2890, "percentage": 53.3},
      {"device": "mobile", "clicks": 2100, "percentage": 38.7}
    ],
    "browsers": [
      {"browser": "Chrome", "clicks": 3250, "percentage": 60.0},
      {"browser": "Safari", "clicks": 1200, "percentage": 22.1}
    ]
  },
  "top_links": [
    {
      "short_code": "abc123",
      "title": "Top Performing Link",
      "clicks": 420,
      "unique_visitors": 380,
      "revenue": 8.40
    }
  ]
}
```

### Get Link Analytics

Detailed analytics for a specific link.

```http
GET /api/v1/analytics/links/abc123?period=7d
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Response:**

```json
{
  "link": {
    "short_code": "abc123",
    "title": "Example Page",
    "total_clicks": 420
  },
  "metrics": {
    "clicks_by_hour": [
      {"hour": "2023-01-01T00:00:00Z", "clicks": 12},
      {"hour": "2023-01-01T01:00:00Z", "clicks": 8}
    ],
    "referrers": [
      {"source": "direct", "clicks": 180, "percentage": 42.9},
      {"source": "twitter.com", "clicks": 95, "percentage": 22.6}
    ],
    "locations": [
      {"country": "US", "region": "California", "city": "San Francisco", "clicks": 85},
      {"country": "UK", "region": "England", "city": "London", "clicks": 42}
    ]
  },
  "revenue": {
    "total_revenue": 8.40,
    "partner_earnings": 4.20,
    "impressions": 420,
    "cpm_rate": 2.00
  }
}
```

### Export Analytics

Export analytics data in various formats.

```http
GET /api/v1/analytics/export?format=csv&period=30d&include=clicks,revenue,demographics
Authorization: Bearer YOUR_ACCESS_TOKEN
```

## Webhooks

Configure webhooks to receive real-time notifications about events.

### Create Webhook

```http
POST /api/v1/webhooks
Authorization: Bearer YOUR_ACCESS_TOKEN
Content-Type: application/json

{
  "url": "https://yourapp.com/webhooks/linkshortener",
  "events": ["link.clicked", "revenue.updated", "link.created"],
  "secret": "your_webhook_secret",
  "active": true
}
```

### Webhook Events

Available webhook events:

- `link.created` - New link created
- `link.clicked` - Link was clicked
- `link.expired` - Link expired
- `revenue.updated` - Revenue calculation updated
- `partner.plan_changed` - Subscription plan changed

### Webhook Payload Example

```json
{
  "event": "link.clicked",
  "timestamp": "2023-01-01T12:00:00Z",
  "data": {
    "short_code": "abc123",
    "original_url": "https://example.com/page",
    "partner_id": "partner_1234567890",
    "visitor": {
      "ip": "192.168.1.1",
      "country": "US",
      "device": "desktop",
      "browser": "Chrome"
    },
    "revenue_generated": 0.02
  }
}
```

## SDKs

### JavaScript SDK

```javascript
import { LinkShortenerAPI } from '@linkshortener/sdk';

const api = new LinkShortenerAPI({
  apiKey: 'your_api_key',
  baseUrl: 'https://api.linkshortener.com'
});

// Shorten a URL
const result = await api.shorten({
  url: 'https://example.com/long-url',
  title: 'My Link'
});

console.log(result.short_url); // https://linkshortener.com/abc123

// Auto-shorten URLs in text
const text = "Check out https://example.com/very/long/url and https://another-example.com/page";
const processedText = await api.autoShortenInText(text);
console.log(processedText); // "Check out https://linkshortener.com/abc123 and https://linkshortener.com/def456"
```

### PHP SDK

```php
use LinkShortener\SDK\Client;

$client = new Client([
    'api_key' => 'your_api_key',
    'base_url' => 'https://api.linkshortener.com'
]);

// Shorten a URL
$result = $client->shorten([
    'url' => 'https://example.com/long-url',
    'title' => 'My Link'
]);

echo $result['short_url']; // https://linkshortener.com/abc123
```

### Python SDK

```python
from linkshortener import LinkShortenerAPI

api = LinkShortenerAPI(
    api_key='your_api_key',
    base_url='https://api.linkshortener.com'
)

# Shorten a URL
result = api.shorten(
    url='https://example.com/long-url',
    title='My Link'
)

print(result['short_url'])  # https://linkshortener.com/abc123
```

## Examples

### Real-time Chat Integration

```javascript
// WebSocket connection for real-time link shortening
const ws = new WebSocket('wss://api.linkshortener.com/ws');

ws.onmessage = function(event) {
  const message = JSON.parse(event.data);
  
  if (message.type === 'message') {
    // Auto-detect and shorten URLs in chat messages
    const processedMessage = await api.autoShortenInText(message.content);
    
    // Display processed message in chat
    displayMessage({
      ...message,
      content: processedMessage
    });
  }
};
```

### Revenue Dashboard Widget

```javascript
// Fetch and display revenue metrics
async function updateRevenueDashboard() {
  const analytics = await api.getAnalytics({
    period: '30d',
    include: ['revenue', 'clicks']
  });
  
  document.getElementById('total-revenue').textContent = 
    `$${analytics.summary.revenue_generated.toFixed(2)}`;
  
  document.getElementById('partner-earnings').textContent = 
    `$${analytics.summary.partner_earnings.toFixed(2)}`;
  
  // Update chart
  updateRevenueChart(analytics.trends.revenue_by_day);
}

// Update every 5 minutes
setInterval(updateRevenueDashboard, 5 * 60 * 1000);
```

### Bulk Link Processing

```javascript
// Process multiple URLs from a file or form
async function processBulkUrls(urls) {
  const batchSize = 100;
  const results = [];
  
  for (let i = 0; i < urls.length; i += batchSize) {
    const batch = urls.slice(i, i + batchSize);
    
    const batchResult = await api.shortenBulk({
      links: batch.map(url => ({ url }))
    });
    
    results.push(...batchResult.results);
    
    // Show progress
    console.log(`Processed ${Math.min(i + batchSize, urls.length)} of ${urls.length} URLs`);
  }
  
  return results;
}
```

## Support

For additional support and questions:

- **Documentation**: https://docs.linkshortener.com
- **API Status**: https://status.linkshortener.com
- **Support Email**: support@linkshortener.com
- **Developer Discord**: https://discord.gg/linkshortener

## Changelog

### v1.0.0 (2023-01-01)
- Initial API release
- Partner registration and authentication
- Link shortening with partner identification
- Basic analytics and revenue tracking
- Webhook support