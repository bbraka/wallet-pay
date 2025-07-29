# Laravel Dusk Testing Research for Laravel Backpack in Docker

## Current Container Analysis

### Existing Setup ✅
- **PHP Container**: Already has Google Chrome Stable installed
- **Chrome User**: Dedicated `chrome` user created for security
- **Environment**: `CHROME_BIN` set to `/usr/bin/google-chrome-stable`
- **Network**: Docker bridge network `user_wallet_network`
- **Services**: app (PHP-FPM), nginx, mysql

### Current Issues to Address
1. **Laravel Dusk Setup**: Laravel Dusk needs to be installed and configured
2. **Network Communication**: Container-to-container communication for testing
3. **Security**: Running Chrome in sandboxed vs non-sandboxed mode
4. **URL Resolution**: Testing URLs need to resolve correctly within container network

## Proposed Docker Configuration Changes

### 1. Add Dedicated Testing Service (Option A - Recommended)

**Create new service in docker-compose.yml:**
```yaml
testing:
  build:
    context: ./docker/php
    dockerfile: Dockerfile.testing
  container_name: user_wallet_testing
  working_dir: /var/www/html
  volumes:
    - .:/var/www/html
  networks:
    - user_wallet_network
  depends_on:
    - nginx
    - app
  environment:
    - PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome-stable
    - APP_URL=http://nginx
  cap_add:
    - SYS_ADMIN  # Required for Chrome sandbox
  profiles:
    - testing  # Only start with --profile testing
```

### 2. Enhanced PHP Container (Option B - Alternative)

**Modify existing app service:**
```yaml
app:
  # ... existing config
  cap_add:
    - SYS_ADMIN  # Add for Chrome sandbox support
  environment:
    - PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome-stable
    - TESTING_BASE_URL=http://nginx
```

### 3. Nginx Configuration Updates

**Add testing-specific location blocks:**
```nginx
# In docker/nginx/default.conf
location /health {
    access_log off;
    return 200 "healthy\n";
    add_header Content-Type text/plain;
}

location /test-status {
    access_log off;
    return 200 "testing ready\n";
    add_header Content-Type text/plain;
}
```

## Testing Strategy Options

### Option 1: MCP Puppeteer Service (Current Approach)
- **Pros**: Uses existing MCP integration, minimal setup
- **Cons**: Limited to MCP capabilities, network complexity
- **Network**: Access via `http://nginx:80` from within container network

### Option 2: Dedicated Testing Container
- **Pros**: Isolated testing environment, full Puppeteer control
- **Cons**: Additional container overhead
- **Implementation**: Separate service with Node.js + Puppeteer

### Option 3: Laravel Dusk Integration
- **Pros**: Native Laravel testing, Backpack-specific helpers
- **Cons**: Requires additional Laravel packages
- **Implementation**: Uses browserless/chrome service

## Recommended Implementation Plan

### Phase 1: Minimal Changes (Immediate)
1. **Add capability to app service**:
   ```yaml
   app:
     cap_add:
       - SYS_ADMIN
   ```

2. **Update environment variables**:
   ```yaml
   environment:
     - TESTING_BASE_URL=http://nginx
   ```

3. **Add nginx health check endpoint**

### Phase 2: Enhanced Testing (Future)
1. **Create dedicated testing service**
2. **Implement Laravel Dusk for Backpack testing**
3. **Add test automation pipeline**

## Network Configuration Strategy

### Internal Container Communication
- **App to Nginx**: `http://nginx:80`
- **Testing to App**: `http://nginx:80`
- **Database**: `mysql:3306`

### External Access (Host)
- **Application**: `http://localhost:8000`
- **Database**: `localhost:3307`

### URL Resolution Matrix
| Context | URL | Purpose |
|---------|-----|---------|
| Host Browser | `http://localhost:8000` | Manual testing |
| Container Puppeteer | `http://nginx:80` | Automated testing |
| MCP Puppeteer | `http://nginx:80` | Current MCP testing |

## Security Considerations

### Chrome Sandbox Mode
- **Production**: Use `--no-sandbox` flag (less secure but functional)
- **Development**: Use `SYS_ADMIN` capability (more secure)
- **Container Isolation**: Dedicated user for Chrome processes

### Network Security
- **Internal Network**: Container-only communication
- **Port Exposure**: Minimal external ports
- **Environment Separation**: Testing profile isolation

## Implementation Checklist

### Immediate (Phase 1)
- [ ] Add `SYS_ADMIN` capability to app service
- [ ] Set `TESTING_BASE_URL` environment variable
- [ ] Add nginx health check endpoint
- [ ] Test MCP Puppeteer with `http://nginx:80`

### Future (Phase 2)
- [ ] Create dedicated testing service
- [ ] Implement Laravel Dusk
- [ ] Add Backpack-specific test helpers
- [ ] Create CI/CD pipeline integration

## Risk Mitigation

### Container Breaking Prevention
1. **Use Docker profiles** for testing services
2. **Separate Dockerfile** for testing container
3. **Environment-specific configs** (.env.testing)
4. **Graceful fallbacks** if testing services fail

### Host Environment Protection
1. **No host port conflicts** (testing uses internal network)
2. **No host file modifications** (containerized testing)
3. **Isolated volumes** for testing data
4. **Clean shutdown** procedures

## Expected Outcomes

### Functional Testing
- ✅ Backpack authentication flows
- ✅ CRUD operations testing
- ✅ Role-based access control validation
- ✅ Frontend JavaScript functionality

### Technical Benefits
- ✅ Reproducible testing environment
- ✅ Container-native testing approach
- ✅ No host environment pollution
- ✅ Scalable for CI/CD integration