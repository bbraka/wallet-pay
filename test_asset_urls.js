const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // Track all network requests
  const requests = [];
  page.on('request', request => {
    const url = request.url();
    if (url.includes('basset') || url.includes('.js') || url.includes('.css')) {
      requests.push({
        url: url,
        method: request.method(),
        resourceType: request.resourceType()
      });
    }
  });

  // Navigate to admin page
  console.log('Navigating to http://localhost:8000/admin...');
  try {
    await page.goto('http://localhost:8000/admin', { 
      waitUntil: 'networkidle',
      timeout: 30000 
    });
    console.log('Page loaded successfully');
  } catch (error) {
    console.error('Error loading page:', error.message);
  }

  // Wait a bit for all requests to complete
  await page.waitForTimeout(2000);

  // Analyze requests
  console.log('\n=== Asset Requests Analysis ===');
  const port8001Requests = requests.filter(r => r.url.includes(':8001'));
  const port8000Requests = requests.filter(r => r.url.includes(':8000'));

  console.log(`\nTotal asset requests: ${requests.length}`);
  console.log(`Requests to port 8001: ${port8001Requests.length}`);
  console.log(`Requests to port 8000: ${port8000Requests.length}`);

  if (port8001Requests.length > 0) {
    console.log('\n=== Assets loading from port 8001 ===');
    port8001Requests.forEach(r => {
      console.log(`- ${r.resourceType}: ${r.url}`);
    });
  }

  // Check page content for hardcoded URLs
  const pageContent = await page.content();
  const matches = pageContent.match(/http:\/\/localhost:8001[^"']*/g);
  if (matches) {
    console.log('\n=== Found port 8001 URLs in page HTML ===');
    [...new Set(matches)].forEach(match => {
      console.log(`- ${match}`);
    });
  }

  // Check for basset URLs in the HTML
  console.log('\n=== Checking how basset URLs are generated ===');
  const bassetUrls = await page.evaluate(() => {
    const scripts = Array.from(document.querySelectorAll('script[src*="basset"]'));
    const links = Array.from(document.querySelectorAll('link[href*="basset"]'));
    return {
      scripts: scripts.map(s => s.src),
      stylesheets: links.map(l => l.href)
    };
  });

  console.log('\nBasset script tags:', bassetUrls.scripts.length);
  bassetUrls.scripts.slice(0, 3).forEach(url => console.log(`- ${url}`));
  
  console.log('\nBasset stylesheet links:', bassetUrls.stylesheets.length);
  bassetUrls.stylesheets.slice(0, 3).forEach(url => console.log(`- ${url}`));

  await browser.close();
})();