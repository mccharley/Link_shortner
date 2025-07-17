# Improved Link Shortener - Architecture Documentation

## Overview

This document provides comprehensive technical documentation for the modernized Link Shortener v2.0, including detailed service flow diagrams, architecture patterns, and system interactions.

## System Architecture

### 1. High-Level Architecture

```mermaid
graph TB
    subgraph "Client Layer"
        A[Web Browser]
        B[Mobile App]
        C[API Client]
    end
    
    subgraph "Load Balancer"
        D[Nginx/HAProxy]
    end
    
    subgraph "Application Layer"
        E[PHP-FPM Instances]
        F[Front Controller]
        G[Router]
        H[Dependency Container]
    end
    
    subgraph "Business Logic"
        I[LinkController]
        J[LinkService]
        K[SecurityService]
        L[UrlValidatorService]
        M[ShortCodeGeneratorService]
    end
    
    subgraph "Data Layer"
        N[LinkRepository]
        O[Cache Layer]
        P[Database]
    end
    
    subgraph "External Services"
        Q[Redis Cache]
        R[MySQL Database]
        S[HTTP Client]
    end
    
    A --> D
    B --> D
    C --> D
    D --> E
    E --> F
    F --> G
    G --> H
    H --> I
    I --> J
    J --> K
    J --> L
    J --> M
    J --> N
    N --> O
    O --> Q
    N --> P
    P --> R
    L --> S
```

### 2. Service Layer Architecture

```mermaid
graph TD
    subgraph "Controller Layer"
        A[LinkController]
        A1[createLink]
        A2[resolveLink]
        A3[getUserLinks]
        A4[deleteLink]
        A5[getAnalytics]
        A6[getSystemStats]
        A7[getCSRFToken]
        
        A --> A1
        A --> A2
        A --> A3
        A --> A4
        A --> A5
        A --> A6
        A --> A7
    end
    
    subgraph "Service Layer"
        B[LinkService]
        C[SecurityService]
        D[UrlValidatorService]
        E[ShortCodeGeneratorService]
        
        B1[createShortLink]
        B2[resolveShortLink]
        B3[getUserLinks]
        B4[deleteLink]
        B5[getAnalytics]
        B6[cleanupExpiredLinks]
        
        C1[generateCSRFToken]
        C2[validateCSRFToken]
        C3[checkRateLimit]
        C4[sanitizeInput]
        C5[generateSecureHeaders]
        
        D1[validateUrl]
        D2[sanitizeUrl]
        D3[isReachable]
        D4[extractDomain]
        
        E1[generateUniqueShortCode]
        E2[generateCustomShortCode]
        E3[validateShortCode]
        E4[analyzeEntropy]
        
        B --> B1
        B --> B2
        B --> B3
        B --> B4
        B --> B5
        B --> B6
        
        C --> C1
        C --> C2
        C --> C3
        C --> C4
        C --> C5
        
        D --> D1
        D --> D2
        D --> D3
        D --> D4
        
        E --> E1
        E --> E2
        E --> E3
        E --> E4
    end
    
    subgraph "Repository Layer"
        F[LinkRepository]
        F1[save]
        F2[findByShortCode]
        F3[findByOriginalUrl]
        F4[findByCreatedBy]
        F5[incrementClickCount]
        F6[deleteByShortCode]
        F7[getStats]
        
        F --> F1
        F --> F2
        F --> F3
        F --> F4
        F --> F5
        F --> F6
        F --> F7
    end
    
    subgraph "Data Layer"
        G[Database]
        H[Cache]
        I[Configuration]
        
        G1[Links Table]
        G2[Click Analytics]
        G3[Users Table]
        G4[Security Events]
        
        H1[Redis Cache]
        H2[Link Cache]
        H3[Rate Limit Cache]
        H4[Validation Cache]
        
        G --> G1
        G --> G2
        G --> G3
        G --> G4
        
        H --> H1
        H --> H2
        H --> H3
        H --> H4
    end
    
    A1 --> B1
    A2 --> B2
    A3 --> B3
    A4 --> B4
    A5 --> B5
    A6 --> B6
    A7 --> C1
    
    B1 --> C3
    B1 --> C4
    B1 --> D1
    B1 --> E1
    B1 --> F1
    
    B2 --> E3
    B2 --> F2
    B2 --> F5
    
    B3 --> F4
    B4 --> F6
    B5 --> F2
    B6 --> F7
    
    F1 --> G1
    F2 --> H2
    F2 --> G1
    F3 --> G1
    F4 --> G1
    F5 --> G1
    F6 --> G1
    F7 --> G1
    
    C3 --> H3
    D3 --> H4
    E1 --> H2
```

## Service Flow Diagrams

### 1. Link Creation Flow

```mermaid
sequenceDiagram
    participant U as User
    participant F as Frontend
    participant C as LinkController
    participant S as SecurityService
    participant LS as LinkService
    participant UV as UrlValidatorService
    participant SCG as ShortCodeGeneratorService
    participant LR as LinkRepository
    participant Cache as Redis Cache
    participant DB as Database
    
    U->>F: Submit URL form
    F->>F: Get CSRF token
    F->>C: POST /api/links
    
    C->>S: checkRateLimit(clientIP)
    S->>Cache: Check rate limit
    Cache-->>S: Rate limit status
    S-->>C: Rate limit result
    
    alt Rate limit exceeded
        C-->>F: 429 Rate Limit Exceeded
        F-->>U: Show error message
    else Rate limit OK
        C->>S: validateCSRFToken(token)
        S-->>C: Token validation result
        
        alt Invalid CSRF token
            C-->>F: 403 Forbidden
            F-->>U: Show error message
        else Valid token
            C->>S: sanitizeInput(url)
            S-->>C: Sanitized URL
            
            C->>LS: createShortLink(url, userId)
            LS->>UV: validateUrl(url)
            UV->>Cache: Check validation cache
            Cache-->>UV: Cached result or null
            
            alt Not cached
                UV->>UV: Validate format
                UV->>UV: Check reachability
                UV->>Cache: Cache validation result
            end
            
            UV-->>LS: Validation result
            
            alt Invalid URL
                LS-->>C: Error response
                C-->>F: 400 Bad Request
                F-->>U: Show error message
            else Valid URL
                LS->>LR: findByOriginalUrl(url)
                LR->>DB: Query existing URL
                DB-->>LR: Query result
                LR-->>LS: Existing link or null
                
                alt URL exists
                    LS-->>C: Return existing link
                else URL doesn't exist
                    LS->>SCG: generateUniqueShortCode()
                    SCG->>Cache: Check code existence
                    Cache-->>SCG: Existence result
                    
                    alt Code exists
                        SCG->>SCG: Generate new code
                    end
                    
                    SCG-->>LS: Unique short code
                    LS->>LR: save(link)
                    LR->>DB: INSERT link
                    DB-->>LR: Save result
                    LR-->>LS: Save confirmation
                    
                    LS->>Cache: Cache link
                    LS-->>C: Success response
                end
                
                C-->>F: 201 Created
                F-->>U: Show success with short URL
            end
        end
    end
```

### 2. Link Resolution Flow

```mermaid
sequenceDiagram
    participant U as User
    participant B as Browser
    participant C as LinkController
    participant S as SecurityService
    participant LS as LinkService
    participant Cache as Redis Cache
    participant LR as LinkRepository
    participant DB as Database
    
    U->>B: Click short link
    B->>C: GET /{shortCode}
    
    C->>S: checkRateLimit(clientIP)
    S->>Cache: Check rate limit
    Cache-->>S: Rate limit status
    S-->>C: Rate limit result
    
    alt Rate limit exceeded
        C-->>B: 429 Rate Limit Exceeded
        B-->>U: Show error page
    else Rate limit OK
        C->>LS: resolveShortLink(shortCode)
        LS->>Cache: getCachedLink(shortCode)
        Cache-->>LS: Cached link or null
        
        alt Link cached
            LS->>LS: Check if active/expired
            alt Link valid
                LS->>LR: incrementClickCount(shortCode)
                LR->>DB: UPDATE click count
                LS->>Cache: Update cached link
                LS-->>C: Original URL
                C->>S: generateSecureHeaders()
                S-->>C: Security headers
                C-->>B: 302 Redirect + headers
                B-->>U: Navigate to original URL
            else Link invalid
                LS-->>C: Error response
                C-->>B: 404 Not Found
                B-->>U: Show error page
            end
        else Link not cached
            LS->>LR: findByShortCode(shortCode)
            LR->>DB: SELECT link
            DB-->>LR: Link data or null
            LR-->>LS: Link object or null
            
            alt Link found
                LS->>Cache: cacheLink(link)
                LS->>LS: Check if active/expired
                alt Link valid
                    LS->>LR: incrementClickCount(shortCode)
                    LR->>DB: UPDATE click count
                    LS-->>C: Original URL
                    C->>S: generateSecureHeaders()
                    S-->>C: Security headers
                    C-->>B: 302 Redirect + headers
                    B-->>U: Navigate to original URL
                else Link invalid
                    LS-->>C: Error response
                    C-->>B: 404 Not Found
                    B-->>U: Show error page
                end
            else Link not found
                LS-->>C: Error response
                C-->>B: 404 Not Found
                B-->>U: Show error page
            end
        end
    end
```

### 3. Security Flow

```mermaid
graph TD
    A[Incoming Request] --> B[IP Blocking Check]
    B --> C{IP Blocked?}
    C -->|Yes| D[403 Forbidden]
    C -->|No| E[Rate Limiting]
    
    E --> F{Rate Limit OK?}
    F -->|No| G[429 Rate Limit Exceeded]
    F -->|Yes| H[CSRF Token Validation]
    
    H --> I{Valid Token?}
    I -->|No| J[403 Forbidden]
    I -->|Yes| K[Input Sanitization]
    
    K --> L[XSS Prevention]
    L --> M[SQL Injection Prevention]
    M --> N[Business Logic Processing]
    
    N --> O[Security Headers]
    O --> P[Response with Security Headers]
    
    subgraph "Security Services"
        Q[SecurityService]
        R[Rate Limiting]
        S[CSRF Protection]
        T[Input Validation]
        U[Output Encoding]
    end
    
    B --> Q
    E --> R
    H --> S
    K --> T
    O --> U
```

### 4. Caching Strategy

```mermaid
graph TD
    subgraph "Cache Layers"
        A[Application Cache]
        B[Database Query Cache]
        C[Validation Cache]
        D[Rate Limit Cache]
        E[Session Cache]
    end
    
    subgraph "Cache Operations"
        F[Read Through]
        G[Write Through]
        H[Cache Aside]
        I[Write Behind]
    end
    
    subgraph "Cache Keys"
        J[link:{shortCode}]
        K[url_reachable:{hash}]
        L[rate_limit:{action}:{ip}]
        M[short_code_exists:{code}]
        N[csrf_token:{token}]
    end
    
    subgraph "TTL Strategy"
        O[Links: 1 hour]
        P[Validation: 1 hour]
        Q[Rate Limits: Window duration]
        R[CSRF Tokens: 1 hour]
        S[Short Code Check: 1 hour]
    end
    
    A --> F
    A --> G
    B --> H
    C --> I
    
    F --> J
    G --> K
    H --> L
    I --> M
    
    J --> O
    K --> P
    L --> Q
    M --> R
    N --> S
```

### 5. Database Schema Relationships

```mermaid
erDiagram
    LINKS {
        bigint id PK
        text original_url
        varchar short_code UK
        timestamp created_at
        timestamp updated_at
        timestamp expires_at
        varchar created_by
        bigint click_count
        boolean is_active
        json metadata
    }
    
    CLICK_ANALYTICS {
        bigint id PK
        bigint link_id FK
        timestamp clicked_at
        varchar ip_address
        text user_agent
        text referer
        varchar country
        varchar city
        varchar device_type
        varchar browser
        varchar os
    }
    
    USERS {
        bigint id PK
        varchar username UK
        varchar email UK
        varchar password_hash
        timestamp created_at
        timestamp updated_at
        timestamp last_login
        boolean is_active
        enum role
        varchar api_key UK
    }
    
    DOMAINS {
        bigint id PK
        varchar domain UK
        boolean is_blocked
        timestamp created_at
        timestamp updated_at
        text block_reason
    }
    
    SECURITY_EVENTS {
        bigint id PK
        varchar event_type
        varchar ip_address
        text user_agent
        timestamp created_at
        enum severity
        json details
    }
    
    SYSTEM_SETTINGS {
        bigint id PK
        varchar setting_key UK
        text setting_value
        timestamp created_at
        timestamp updated_at
    }
    
    LINKS ||--o{ CLICK_ANALYTICS : "generates"
    USERS ||--o{ LINKS : "creates"
    DOMAINS ||--o{ LINKS : "belongs_to"
    USERS ||--o{ SECURITY_EVENTS : "triggers"
```

### 6. API Request/Response Flow

```mermaid
sequenceDiagram
    participant Client
    participant Router
    participant Controller
    participant Middleware
    participant Service
    participant Repository
    participant Database
    participant Cache
    
    Client->>Router: HTTP Request
    Router->>Controller: Route to method
    Controller->>Middleware: Security checks
    
    Middleware->>Cache: Check rate limit
    Cache-->>Middleware: Rate limit status
    
    alt Rate limit exceeded
        Middleware-->>Controller: 429 Error
        Controller-->>Client: Rate limit response
    else Rate limit OK
        Middleware->>Middleware: Validate CSRF token
        Middleware->>Middleware: Sanitize input
        Middleware-->>Controller: Proceed
        
        Controller->>Service: Business logic call
        Service->>Repository: Data operation
        Repository->>Cache: Check cache
        Cache-->>Repository: Cached data or miss
        
        alt Cache miss
            Repository->>Database: Query data
            Database-->>Repository: Data result
            Repository->>Cache: Update cache
        end
        
        Repository-->>Service: Data result
        Service->>Service: Process business logic
        Service-->>Controller: Service result
        
        Controller->>Controller: Format response
        Controller->>Controller: Add security headers
        Controller-->>Client: HTTP Response
    end
```

### 7. Error Handling Flow

```mermaid
graph TD
    A[Request Processing] --> B{Error Occurred?}
    B -->|No| C[Success Response]
    B -->|Yes| D[Error Type Check]
    
    D --> E{Validation Error?}
    E -->|Yes| F[400 Bad Request]
    
    D --> G{Authentication Error?}
    G -->|Yes| H[401 Unauthorized]
    
    D --> I{Authorization Error?}
    I -->|Yes| J[403 Forbidden]
    
    D --> K{Resource Not Found?}
    K -->|Yes| L[404 Not Found]
    
    D --> M{Rate Limit Error?}
    M -->|Yes| N[429 Too Many Requests]
    
    D --> O{Server Error?}
    O -->|Yes| P[500 Internal Server Error]
    
    subgraph "Error Handling"
        Q[Log Error]
        R[Sanitize Error Message]
        S[Security Headers]
        T[JSON Response]
    end
    
    F --> Q
    H --> Q
    J --> Q
    L --> Q
    N --> Q
    P --> Q
    
    Q --> R
    R --> S
    S --> T
    T --> U[Error Response]
```

### 8. Performance Monitoring Flow

```mermaid
graph TD
    subgraph "Metrics Collection"
        A[Request Metrics]
        B[Database Metrics]
        C[Cache Metrics]
        D[Error Metrics]
        E[Security Metrics]
    end
    
    subgraph "Monitoring Points"
        F[Request Duration]
        G[Database Query Time]
        H[Cache Hit Rate]
        I[Error Rate]
        J[Rate Limit Hits]
        K[Security Events]
    end
    
    subgraph "Alerting"
        L[Performance Alerts]
        M[Error Alerts]
        N[Security Alerts]
        O[Capacity Alerts]
    end
    
    subgraph "Dashboards"
        P[System Health]
        Q[Performance Metrics]
        R[Security Dashboard]
        S[Usage Analytics]
    end
    
    A --> F
    B --> G
    C --> H
    D --> I
    E --> J
    E --> K
    
    F --> L
    G --> L
    H --> L
    I --> M
    J --> N
    K --> N
    
    L --> P
    M --> Q
    N --> R
    O --> S
```

## Component Interactions

### 1. Dependency Injection Container

```mermaid
graph TD
    subgraph "Container"
        A[DI Container]
        B[Service Registry]
        C[Singleton Manager]
        D[Factory Methods]
    end
    
    subgraph "Services"
        E[LinkService]
        F[SecurityService]
        G[UrlValidatorService]
        H[ShortCodeGeneratorService]
        I[LinkRepository]
        J[Logger]
    end
    
    subgraph "Dependencies"
        K[Database Connection]
        L[Redis Client]
        M[HTTP Client]
        N[Configuration]
    end
    
    A --> B
    B --> C
    C --> D
    
    D --> E
    D --> F
    D --> G
    D --> H
    D --> I
    D --> J
    
    E --> I
    E --> F
    E --> G
    E --> H
    E --> J
    
    I --> K
    F --> L
    G --> M
    All --> N
```

### 2. Configuration Management

```mermaid
graph TD
    subgraph "Configuration Sources"
        A[Environment Variables]
        B[.env File]
        C[System Settings Table]
        D[Runtime Configuration]
    end
    
    subgraph "Configuration Categories"
        E[Database Config]
        F[Cache Config]
        G[Security Config]
        H[Application Config]
        I[Logging Config]
    end
    
    subgraph "Configuration Usage"
        J[Database Connection]
        K[Redis Connection]
        L[Security Services]
        M[Application Services]
        N[Logger Setup]
    end
    
    A --> E
    B --> F
    C --> G
    D --> H
    
    E --> J
    F --> K
    G --> L
    H --> M
    I --> N
```

## Deployment Architecture

### 1. Production Deployment

```mermaid
graph TB
    subgraph "Load Balancer"
        A[Nginx/HAProxy]
        B[SSL Termination]
        C[Request Routing]
    end
    
    subgraph "Application Servers"
        D[App Server 1]
        E[App Server 2]
        F[App Server N]
    end
    
    subgraph "Database Cluster"
        G[MySQL Master]
        H[MySQL Slave 1]
        I[MySQL Slave 2]
    end
    
    subgraph "Cache Cluster"
        J[Redis Master]
        K[Redis Slave 1]
        L[Redis Slave 2]
    end
    
    subgraph "Monitoring"
        M[Application Monitoring]
        N[Database Monitoring]
        O[Cache Monitoring]
        P[Security Monitoring]
    end
    
    A --> B
    B --> C
    C --> D
    C --> E
    C --> F
    
    D --> G
    E --> H
    F --> I
    
    D --> J
    E --> K
    F --> L
    
    D --> M
    G --> N
    J --> O
    A --> P
```

### 2. Scaling Strategy

```mermaid
graph TD
    subgraph "Horizontal Scaling"
        A[Load Balancer]
        B[Auto Scaling Group]
        C[Application Instances]
        D[Database Read Replicas]
        E[Cache Sharding]
    end
    
    subgraph "Vertical Scaling"
        F[CPU Scaling]
        G[Memory Scaling]
        H[Storage Scaling]
        I[Network Scaling]
    end
    
    subgraph "Performance Optimization"
        J[CDN Integration]
        K[Database Optimization]
        L[Cache Optimization]
        M[Code Optimization]
    end
    
    A --> B
    B --> C
    C --> D
    C --> E
    
    C --> F
    C --> G
    D --> H
    E --> I
    
    A --> J
    D --> K
    E --> L
    C --> M
```

This comprehensive documentation provides detailed insights into the improved architecture, showing how the modern implementation addresses all the original limitations while providing a scalable, secure, and maintainable solution.