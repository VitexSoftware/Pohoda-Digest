# Pohoda-Digest Examples

This directory contains practical examples showing how to use Pohoda-Digest for analytics and reporting.

## Examples Overview

### 1. `basic_usage.php`
**Simple analytics generation**
- Connect to Pohoda mServer
- Run built-in analytics modules
- Generate HTML reports (Bootstrap and Email themes)
- Save JSON data for further processing

**Usage:**
```bash
cd examples/
php basic_usage.php
```

**Requirements:**
- Pohoda mServer running and accessible
- Update connection configuration in the script

### 2. `advanced_usage.php`
**Production-ready analytics with error handling**
- Environment variable configuration
- Comprehensive error handling
- Email notifications
- Summary statistics
- Multiple output formats

**Usage:**
```bash
# Set environment variables
export POHODA_HOST=localhost
export POHODA_PORT=5435
export POHODA_DATABASE=StwPh_12345678_2024
export SMTP_HOST=mail.yourcompany.com
export FROM_EMAIL=reports@yourcompany.com
export TO_EMAILS=manager@company.com,cfo@company.com

# Run the script
php advanced_usage.php
```

## Configuration Options

### Pohoda Connection
```bash
POHODA_HOST=localhost          # Pohoda mServer hostname
POHODA_PORT=5435              # mServer port (default: 5435)
POHODA_DATABASE=StwPh_12345678_2024  # Database identifier
```

### Email Notifications
```bash
SMTP_HOST=mail.company.com    # SMTP server
SMTP_PORT=587                 # SMTP port
FROM_EMAIL=reports@company.com # Sender email
TO_EMAILS=user1@company.com,user2@company.com  # Recipients (comma-separated)
```

### Other Settings
```bash
CACHE_TTL=3600               # Cache time-to-live in seconds
OUTPUT_DIR=/var/reports      # Output directory for generated reports
```

## Output Files

Generated files will be saved to the `output/` directory:

- **Web Reports**: `web_report.html` - Bootstrap theme for web display
- **Email Reports**: `email_report_YYYY-MM-DD_HH-mm-ss.html` - Email-compatible HTML
- **JSON Data**: `analytics_data.json` - Raw analytics data for API use

## Cron Job Setup

For automated daily reports, add to your crontab:

```bash
# Daily report at 6:00 AM
0 6 * * * cd /path/to/pohoda-digest/examples && php advanced_usage.php >> /var/log/pohoda-digest.log 2>&1
```

## Troubleshooting

### Connection Issues
- Verify Pohoda mServer is running
- Check firewall settings for port 5435
- Ensure database identifier is correct

### Permission Issues
- Ensure output directory is writable
- Check file permissions for generated reports

### Email Issues
- Verify SMTP configuration
- Check email server logs
- Test with a simple email client first

## Customization

### Adding Custom Modules
See DigestModules documentation for creating custom analytics modules.

### Custom Themes
See DigestRenderer documentation for creating custom HTML themes.

### Integration Examples
- API endpoints serving JSON data
- Dashboard widgets using analytics data
- Custom notification systems

## Security Considerations

### Production Deployment
- Store sensitive configuration in environment variables or secure config files
- Use secure SMTP authentication
- Implement proper logging and monitoring
- Regular backup of generated reports

### Access Control
- Restrict access to analytics data
- Use HTTPS for email reports containing sensitive data
- Implement user authentication for web-based reports

## Support

For issues and questions:
- Check the main README.md file
- Review DigestModules and DigestRenderer documentation  
- Submit issues on GitHub: https://github.com/VitexSoftware/Pohoda-Digest