# LinkShortener Service Flows Documentation

## Table of Contents

1. [Overview](#overview)
2. [Partner Onboarding Flow](#partner-onboarding-flow)
3. [Authentication Flows](#authentication-flows)
4. [Link Creation and Management Flow](#link-creation-and-management-flow)
5. [Revenue Generation Flow](#revenue-generation-flow)
6. [Advertisement Display Flow](#advertisement-display-flow)
7. [Analytics and Reporting Flow](#analytics-and-reporting-flow)
8. [Payment and Subscription Flow](#payment-and-subscription-flow)
9. [Real-time Integration Flow](#real-time-integration-flow)
10. [Error Handling and Recovery Flows](#error-handling-and-recovery-flows)

## Overview

This document outlines the key service flows for the LinkShortener API system, detailing how partners interact with the platform, how revenue is generated, and how the various components work together to provide a seamless experience.

## Partner Onboarding Flow

### 1. Registration Process

```mermaid
graph TD
    A[Partner visits registration page] --> B[Fills registration form]
    B --> C[Selects subscription plan]
    C --> D[Provides billing information]
    D --> E[Submits registration]
    E --> F[System validates input]
    F --> G{Validation successful?}
    G -->|No| H[Show validation errors]
    H --> B
    G -->|Yes| I[Create partner account]
    I --> J[Generate API credentials]
    J --> K[Send verification email]
    K --> L[Create Stripe customer]
    L --> M[Process initial payment]
    M --> N{Payment successful?}
    N -->|No| O[Show payment error]
    O --> D
    N -->|Yes| P[Activate account]
    P --> Q[Send welcome email]
    Q --> R[Redirect to dashboard]
```

#### Step-by-Step Process

1. **Initial Registration**
   - Partner fills out company information
   - System validates email uniqueness
   - Password strength requirements enforced
   - Business type selection (individual/company/organization)

2. **Plan Selection**
   - Display available subscription plans
   - Show feature comparison
   - Calculate pricing (monthly vs yearly)
   - Apply any promotional discounts

3. **Payment Setup**
   - Collect billing address
   - Integrate with Stripe for payment processing
   - Support multiple payment methods
   - Handle payment failures gracefully

4. **Account Activation**
   - Generate unique partner ID
   - Create API key and secret
   - Set default revenue sharing (50%)
   - Initialize analytics tracking

5. **Email Verification**
   - Send verification email with secure token
   - Token expires in 24 hours
   - Resend functionality available
   - Account fully activated after verification

### 2. Onboarding Tutorial Flow

```mermaid
sequenceDiagram
    participant P as Partner
    participant S as System
    participant E as Email Service
    participant D as Dashboard
    
    P->>S: Complete registration
    S->>E: Send welcome email
    S->>D: Show onboarding wizard
    D->>P: Step 1: API Integration Guide
    P->>D: Mark step complete
    D->>P: Step 2: Test API call
    P->>S: Make test API request
    S->>P: Return test response
    D->>P: Step 3: Configure webhooks
    P->>D: Set webhook URL
    D->>P: Step 4: Review analytics
    P->>D: Complete onboarding
    S->>E: Send completion confirmation
```

## Authentication Flows

### 1. API Key Authentication

```mermaid
graph TD
    A[Client makes API request] --> B[Include API key in header]
    B --> C[System validates API key]
    C --> D{Key valid?}
    D -->|No| E[Return 401 Unauthorized]
    D -->|Yes| F[Check rate limits]
    F --> G{Within limits?}
    G -->|No| H[Return 429 Rate Limited]
    G -->|Yes| I[Process request]
    I --> J[Return response]
```

### 2. OAuth 2.0 Flow

```mermaid
sequenceDiagram
    participant C as Client App
    participant A as Auth Server
    participant R as Resource Server
    participant U as User
    
    C->>A: Request authorization
    A->>U: Show login form
    U->>A: Enter credentials
    A->>U: Show consent screen
    U->>A: Grant permission
    A->>C: Return authorization code
    C->>A: Exchange code for token
    A->>C: Return access token
    C->>R: API request with token
    R->>A: Validate token
    A->>R: Token valid
    R->>C: Return API response
```

### 3. Multi-Factor Authentication Flow

```mermaid
graph TD
    A[Partner enters credentials] --> B[System validates password]
    B --> C{Password valid?}
    C -->|No| D[Return login error]
    C -->|Yes| E{MFA enabled?}
    E -->|No| F[Generate session token]
    E -->|Yes| G[Request MFA code]
    G --> H[Partner enters TOTP code]
    H --> I[System validates TOTP]
    I --> J{TOTP valid?}
    J -->|No| K[Return MFA error]
    J -->|Yes| F
    F --> L[Set session cookie]
    L --> M[Redirect to dashboard]
```

## Link Creation and Management Flow

### 1. Standard Link Creation

```mermaid
sequenceDiagram
    participant C as Client
    participant A as API
    participant V as Validator
    participant G as Generator
    participant D as Database
    participant R as Redis
    
    C->>A: POST /api/v1/shorten
    A->>V: Validate URL and parameters
    V->>A: Validation result
    A->>G: Generate short code with partner ID
    G->>A: Return unique short code
    A->>D: Store URL mapping
    D->>A: Confirm storage
    A->>R: Cache mapping
    R->>A: Confirm cache
    A->>C: Return short URL
```

### 2. Partner-Identified Link Generation

```mermaid
graph TD
    A[Receive shorten request] --> B[Extract partner ID from API key]
    B --> C[Generate partner hash (0-999)]
    C --> D[Generate random URL ID]
    D --> E[Encode: (URL_ID * 1000) + partner_hash]
    E --> F[Convert to base36]
    F --> G[Pad to desired length]
    G --> H{Short code unique?}
    H -->|No| I[Increment attempt counter]
    I --> J{Max attempts reached?}
    J -->|Yes| K[Return error]
    J -->|No| D
    H -->|Yes| L[Store mapping in database]
    L --> M[Cache in Redis]
    M --> N[Return short URL]
```

### 3. Bulk Link Creation Flow

```mermaid
graph TD
    A[Receive bulk request] --> B[Validate request size]
    B --> C{Within limits?}
    C -->|No| D[Return error]
    C -->|Yes| E[Process in batches]
    E --> F[For each URL in batch]
    F --> G[Validate URL]
    G --> H{Valid?}
    H -->|No| I[Add to failed list]
    H -->|Yes| J[Generate short code]
    J --> K[Store in database]
    K --> L[Add to success list]
    L --> M{More URLs?}
    M -->|Yes| F
    M -->|No| N[Return batch results]
    I --> M
```

## Revenue Generation Flow

### 1. Click-to-Revenue Process

```mermaid
sequenceDiagram
    participant U as User
    participant S as System
    participant A as Ad Server
    participant D as Database
    participant P as Partner
    
    U->>S: Click short link
    S->>D: Look up original URL
    S->>A: Request advertisement
    A->>S: Return ad content
    S->>U: Display ad page
    Note over U,S: User views ad for configured duration
    U->>S: Click continue (after timer)
    S->>D: Record click analytics
    S->>D: Calculate revenue
    S->>P: Update partner earnings
    S->>U: Redirect to original URL
```

### 2. Revenue Calculation Flow

```mermaid
graph TD
    A[Click recorded] --> B[Get advertisement CPM rate]
    B --> C[Calculate gross revenue: CPM/1000]
    C --> D[Get partner revenue share]
    D --> E[Calculate partner earnings: gross * share]
    E --> F[Calculate service earnings: gross - partner]
    F --> G[Update click analytics record]
    G --> H[Update partner revenue total]
    H --> I[Update advertisement metrics]
    I --> J[Trigger revenue webhook]
    J --> K[Check payout threshold]
    K --> L{Threshold reached?}
    L -->|Yes| M[Queue payout process]
    L -->|No| N[Continue monitoring]
```

### 3. Monthly Revenue Reconciliation

```mermaid
graph TD
    A[Start of month] --> B[Run revenue calculation job]
    B --> C[For each partner]
    C --> D[Calculate previous month totals]
    D --> E[Validate against click records]
    E --> F[Generate revenue report]
    F --> G[Create payout record]
    G --> H{Partner eligible for payout?}
    H -->|No| I[Mark as ineligible]
    H -->|Yes| J[Process payout via Stripe]
    J --> K{Payout successful?}
    K -->|No| L[Retry payout]
    K -->|Yes| M[Update payout status]
    M --> N[Send payout notification]
    N --> O{More partners?}
    O -->|Yes| C
    O -->|No| P[Complete reconciliation]
```

## Advertisement Display Flow

### 1. Ad Selection Process

```mermaid
graph TD
    A[User clicks short link] --> B[Determine user location]
    B --> C[Detect device type]
    C --> D[Query active advertisements]
    D --> E[Filter by targeting criteria]
    E --> F[Apply budget constraints]
    F --> G{Ads available?}
    G -->|No| H[Use default ad]
    G -->|Yes| I[Select ad using algorithm]
    I --> J[Check ad frequency caps]
    J --> K{Within frequency limits?}
    K -->|No| L[Select alternative ad]
    K -->|Yes| M[Return selected ad]
    L --> G
    H --> M
    M --> N[Increment impression count]
    N --> O[Display ad to user]
```

### 2. Ad Display and Interaction

```mermaid
sequenceDiagram
    participant U as User
    participant A as Ad Page
    participant T as Timer
    participant S as System
    participant D as Database
    
    U->>A: Land on ad page
    A->>T: Start countdown timer
    A->>S: Record impression
    S->>D: Update ad metrics
    T->>A: Update countdown display
    A->>U: Show countdown (15s, 14s, 13s...)
    T->>A: Timer reaches 0
    A->>U: Enable continue button
    U->>A: Click continue
    A->>S: Record interaction
    S->>D: Update click metrics
    A->>U: Redirect to original URL
```

### 3. Ad Performance Tracking

```mermaid
graph TD
    A[Ad impression recorded] --> B[Update impression count]
    B --> C[Calculate CTR]
    C --> D[Update revenue metrics]
    D --> E[Check budget limits]
    E --> F{Budget exceeded?}
    F -->|Yes| G[Pause advertisement]
    F -->|No| H[Continue serving]
    G --> I[Notify advertiser]
    H --> J[Update performance stats]
    J --> K[Trigger analytics webhook]
    K --> L[Store in time-series data]
```

## Analytics and Reporting Flow

### 1. Real-time Analytics Collection

```mermaid
sequenceDiagram
    participant E as Event Source
    participant Q as Queue
    participant P as Processor
    participant D as Database
    participant C as Cache
    participant A as Analytics API
    
    E->>Q: Publish click event
    Q->>P: Process event
    P->>D: Store raw event data
    P->>C: Update real-time counters
    P->>D: Update aggregated metrics
    D->>A: Data available for queries
    C->>A: Real-time data available
```

### 2. Analytics Dashboard Data Flow

```mermaid
graph TD
    A[Dashboard request] --> B[Authenticate partner]
    B --> C[Get date range parameters]
    C --> D[Query aggregated data]
    D --> E[Query real-time metrics]
    E --> F[Combine data sources]
    F --> G[Apply partner filters]
    G --> H[Calculate derived metrics]
    H --> I[Format for visualization]
    I --> J[Cache results]
    J --> K[Return to dashboard]
```

### 3. Report Generation Flow

```mermaid
graph TD
    A[Partner requests report] --> B[Validate parameters]
    B --> C[Queue report job]
    C --> D[Fetch data from multiple sources]
    D --> E[Process and aggregate]
    E --> F[Apply filters and grouping]
    F --> G[Generate charts/graphs]
    G --> H[Format output (PDF/CSV/Excel)]
    H --> I[Store report file]
    I --> J[Send download link]
    J --> K[Schedule cleanup]
```

## Payment and Subscription Flow

### 1. Subscription Creation

```mermaid
sequenceDiagram
    participant P as Partner
    participant S as System
    participant St as Stripe
    participant D as Database
    
    P->>S: Select subscription plan
    S->>St: Create customer
    St->>S: Return customer ID
    S->>D: Store customer mapping
    S->>St: Create subscription
    St->>S: Return subscription details
    S->>D: Store subscription
    S->>P: Confirm subscription
    St->>S: Send webhook (subscription.created)
    S->>D: Update subscription status
```

### 2. Payment Processing Flow

```mermaid
graph TD
    A[Billing cycle starts] --> B[Stripe processes payment]
    B --> C{Payment successful?}
    C -->|Yes| D[Update subscription status]
    C -->|No| E[Mark as past due]
    D --> F[Reset usage counters]
    F --> G[Send invoice email]
    G --> H[Continue service]
    E --> I[Send payment failed email]
    I --> J[Allow retry period]
    J --> K{Payment retried?}
    K -->|Yes| B
    K -->|No| L[Suspend account]
    L --> M[Send suspension notice]
```

### 3. Plan Upgrade/Downgrade Flow

```mermaid
sequenceDiagram
    participant P as Partner
    participant S as System
    participant St as Stripe
    participant D as Database
    
    P->>S: Request plan change
    S->>D: Check current usage
    S->>P: Show prorated amount
    P->>S: Confirm change
    S->>St: Update subscription
    St->>S: Return updated subscription
    S->>D: Update partner plan
    S->>D: Adjust rate limits
    S->>P: Confirm plan change
    St->>S: Webhook (subscription.updated)
    S->>P: Send confirmation email
```

## Real-time Integration Flow

### 1. Chat Application Integration

```mermaid
sequenceDiagram
    participant U as User
    participant C as Chat App
    participant W as WebSocket
    participant A as API
    participant S as System
    
    U->>C: Type message with URL
    C->>A: Auto-detect URLs
    A->>S: Shorten detected URLs
    S->>A: Return short URLs
    A->>C: Replace URLs in message
    C->>W: Send processed message
    W->>C: Broadcast to recipients
    C->>U: Display message with short URLs
```

### 2. WebSocket Real-time Updates

```mermaid
graph TD
    A[Partner connects to WebSocket] --> B[Authenticate connection]
    B --> C[Subscribe to partner events]
    C --> D[Link clicked]
    D --> E[Generate event payload]
    E --> F[Send to WebSocket]
    F --> G[Partner receives update]
    G --> H[Update dashboard metrics]
    H --> I[Display notification]
```

### 3. SDK Integration Flow

```mermaid
sequenceDiagram
    participant A as App
    participant S as SDK
    participant C as Cache
    participant API as API Server
    
    A->>S: Initialize SDK with API key
    S->>API: Validate API key
    API->>S: Return validation result
    A->>S: Call shorten method
    S->>C: Check local cache
    C->>S: Cache miss
    S->>API: Make API request
    API->>S: Return short URL
    S->>C: Cache result
    S->>A: Return short URL
```

## Error Handling and Recovery Flows

### 1. API Error Handling

```mermaid
graph TD
    A[API request received] --> B[Validate request]
    B --> C{Valid?}
    C -->|No| D[Generate error response]
    C -->|Yes| E[Process request]
    E --> F{Processing successful?}
    F -->|No| G[Determine error type]
    F -->|Yes| H[Return success response]
    G --> I{Retryable error?}
    I -->|Yes| J[Add to retry queue]
    I -->|No| D
    J --> K[Wait for retry interval]
    K --> E
    D --> L[Log error]
    L --> M[Return error to client]
```

### 2. Database Failure Recovery

```mermaid
graph TD
    A[Database operation fails] --> B[Check connection status]
    B --> C{Connection alive?}
    C -->|No| D[Attempt reconnection]
    C -->|Yes| E[Retry operation]
    D --> F{Reconnection successful?}
    F -->|No| G[Switch to backup database]
    F -->|Yes| E
    E --> H{Retry successful?}
    H -->|Yes| I[Continue normal operation]
    H -->|No| J[Check retry count]
    J --> K{Max retries reached?}
    K -->|No| L[Wait and retry]
    K -->|Yes| M[Return error to client]
    L --> E
    G --> N[Update DNS/load balancer]
    N --> O[Continue with backup]
```

### 3. Payment Failure Recovery

```mermaid
sequenceDiagram
    participant S as System
    participant St as Stripe
    participant P as Partner
    participant E as Email Service
    
    St->>S: Payment failed webhook
    S->>S: Mark subscription as past_due
    S->>E: Send payment failed email
    E->>P: Receive email notification
    P->>S: Update payment method
    S->>St: Retry payment
    St->>S: Payment successful webhook
    S->>S: Reactivate subscription
    S->>E: Send payment success email
    E->>P: Receive confirmation
```

### 4. Rate Limit Handling

```mermaid
graph TD
    A[API request received] --> B[Check rate limit]
    B --> C{Within limits?}
    C -->|Yes| D[Process request]
    C -->|No| E[Return 429 status]
    E --> F[Include retry-after header]
    F --> G[Log rate limit violation]
    G --> H{Excessive violations?}
    H -->|Yes| I[Temporary IP ban]
    H -->|No| J[Continue monitoring]
    I --> K[Send abuse notification]
    K --> L[Review account status]
```

### 5. Service Degradation Flow

```mermaid
graph TD
    A[High system load detected] --> B[Enable degraded mode]
    B --> C[Disable non-essential features]
    C --> D[Increase cache TTL]
    D --> E[Reduce analytics granularity]
    E --> F[Queue non-critical operations]
    F --> G[Send system status update]
    G --> H[Monitor system metrics]
    H --> I{Load decreased?}
    I -->|No| J[Scale up resources]
    J --> H
    I -->|Yes| K[Gradually restore features]
    K --> L[Return to normal operation]
```

## Monitoring and Alerting Flows

### 1. Health Check Flow

```mermaid
graph TD
    A[Health check request] --> B[Check database connectivity]
    B --> C[Check Redis connectivity]
    C --> D[Check external APIs]
    D --> E[Check disk space]
    E --> F[Check memory usage]
    F --> G[Aggregate health status]
    G --> H{All systems healthy?}
    H -->|Yes| I[Return 200 OK]
    H -->|No| J[Return 503 Service Unavailable]
    J --> K[Include unhealthy components]
    K --> L[Trigger alerts]
```

### 2. Alert Escalation Flow

```mermaid
sequenceDiagram
    participant M as Monitor
    participant A as Alert System
    participant E as Email
    participant S as SMS
    participant P as PagerDuty
    
    M->>A: Threshold exceeded
    A->>E: Send email alert
    Note over A: Wait 5 minutes
    A->>A: Check if resolved
    A->>S: Send SMS alert
    Note over A: Wait 10 minutes
    A->>A: Check if resolved
    A->>P: Create incident
    P->>A: Acknowledge receipt
```

This comprehensive service flow documentation provides a complete picture of how the LinkShortener API system operates, from partner onboarding through revenue generation and error handling. Each flow is designed to be robust, scalable, and user-friendly while maintaining security and performance standards.