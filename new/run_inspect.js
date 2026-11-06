import http from 'http';
import fs from 'fs';
import path from 'path';
import { spawn } from 'child_process';

const DIST_DIR = '/Users/home/Documents/myvprNew/new/dist';
const PORT = 4173;

function handleApi(url, res) {
  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Credentials', 'true');

  if (url.includes('/user/profile')) {
    res.end(JSON.stringify({
      success: true,
      data: {
        id: 1,
        name: 'DanOo',
        username: 'danoo',
        email: 'danoo@vpr.org',
        is_admin: true,
        isSubscribed: true,
        subscription_status: 'active',
        totalViews: 42
      }
    }));
    return true;
  }
  if (url.includes('/streams/live')) {
    res.end(JSON.stringify({
      success: true,
      streams: [{
        id: 1,
        title: 'Healing Streams Live Healing Services',
        description: 'With Pastor Chris',
        stream_url: 'https://example.com/hls.m3u8',
        is_live: 1,
        thumbnail_url: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80'
      }]
    }));
    return true;
  }
  if (url.includes('/videos/languages')) {
    res.end(JSON.stringify({
      success: true,
      data: ['English', 'French', 'Spanish']
    }));
    return true;
  }
  if (url.includes('/videos')) {
    res.end(JSON.stringify({
      success: true,
      data: []
    }));
    return true;
  }
  if (url.includes('/crusades')) {
    res.end(JSON.stringify({
      success: true,
      data: { scheduled: [], active: [], past: [] }
    }));
    return true;
  }
  if (url.includes('/meetings')) {
    res.end(JSON.stringify({
      success: true,
      data: []
    }));
    return true;
  }

  res.end(JSON.stringify({ success: true, data: [] }));
  return true;
}

const server = http.createServer((req, res) => {
  const url = req.url.split('?')[0];

  if (url.startsWith('/api')) {
    return handleApi(req.url, res);
  }

  let filePath = path.join(DIST_DIR, url === '/' ? 'index.html' : url);
  if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
    filePath = path.join(DIST_DIR, 'index.html');
  }

  const ext = path.extname(filePath);
  const mimeTypes = {
    '.html': 'text/html',
    '.js': 'application/javascript',
    '.css': 'text/css',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.json': 'application/json'
  };

  res.setHeader('Content-Type', mimeTypes[ext] || 'application/octet-stream');
  fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, async () => {
  console.log(`Server listening on http://localhost:${PORT}`);

  const chromePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
  const tmpDir = `/tmp/chrome_diag_${Date.now()}`;
  fs.mkdirSync(tmpDir, { recursive: true });

  const chrome = spawn(chromePath, [
    '--headless=new',
    '--no-sandbox',
    '--disable-gpu',
    `--user-data-dir=${tmpDir}`,
    '--remote-debugging-port=9333',
    'about:blank'
  ]);

  // Wait for Chrome to bind
  let wsUrl = null;
  for (let i = 0; i < 20; i++) {
    await new Promise(r => setTimeout(r, 200));
    try {
      const res = await fetch('http://127.0.0.1:9333/json/version');
      const data = await res.json();
      wsUrl = data.webSocketDebuggerUrl;
      if (wsUrl) break;
    } catch {}
  }

  if (!wsUrl) {
    console.error('Failed to get WebSocket debugger URL');
    chrome.kill();
    server.close();
    process.exit(1);
  }

  console.log('Connected to Chrome via CDP:', wsUrl);
  const ws = new WebSocket(wsUrl);
  await new Promise(res => ws.onopen = res);

  let id = 1;
  function send(method, params = {}) {
    return new Promise(res => {
      const msgId = id++;
      const handler = (evt) => {
        const data = JSON.parse(evt.data);
        if (data.id === msgId) {
          ws.removeEventListener('message', handler);
          res(data.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id: msgId, method, params }));
    });
  }

  // Create new target/page
  const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });

  function sendPage(method, params = {}) {
    return new Promise(res => {
      const msgId = id++;
      const handler = (evt) => {
        const data = JSON.parse(evt.data);
        if (data.id === msgId) {
          ws.removeEventListener('message', handler);
          res(data.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id: msgId, sessionId, method, params }));
    });
  }

  await sendPage('Page.enable');
  await sendPage('DOM.enable');

  const viewports = [
    { name: '1280x800', width: 1280, height: 800 },
    { name: '1366x768', width: 1366, height: 768 },
    { name: '1440x900', width: 1440, height: 900 },
    { name: '1920x1080', width: 1920, height: 1080 },
    { name: 'tablet-768x1024', width: 768, height: 1024 },
    { name: 'mobile-375x667', width: 375, height: 667 },
  ];

  for (const pagePath of ['/dashboard', '/videos']) {
    console.log(`\n=================== TESTING ${pagePath} ===================`);
    for (const vp of viewports) {
      await sendPage('Emulation.setDeviceMetricsOverride', {
        width: vp.width,
        height: vp.height,
        deviceScaleFactor: 1,
        mobile: vp.name.startsWith('mobile')
      });

      await sendPage('Page.navigate', { url: `http://localhost:${PORT}${pagePath}` });
      // Wait for content to render
      for (let i = 0; i < 30; i++) {
        await new Promise(r => setTimeout(r, 100));
        const check = await sendPage('Runtime.evaluate', {
          expression: `Boolean(document.querySelector('h1'))`
        });
        if (check.result.value) {
          await new Promise(r => setTimeout(r, 200));
          break;
        }
      }

      const diag = await sendPage('Runtime.evaluate', {
        expression: `(() => {
          const docEl = document.documentElement;
          const body = document.body;
          const winW = window.innerWidth;
          const scrollW = docEl.scrollWidth;
          const bodyScrollW = body.scrollWidth;
          const hasHScroll = scrollW > winW || bodyScrollW > winW;

          // Find elements whose bounding box extends past the right edge
          const overflowing = [];
          const all = document.querySelectorAll('*');
          for (const el of all) {
            const rect = el.getBoundingClientRect();
            if (rect.right > winW + 2) {
              overflowing.push({
                tag: el.tagName,
                className: (el.className || '').toString().slice(0, 80),
                right: Math.round(rect.right),
                width: Math.round(rect.width),
                overflowPx: Math.round(rect.right - winW)
              });
            }
          }

          // Check hero card vs right column overlap
          let heroOverlap = null;
          const heroCol = document.querySelector('.lg\\\\:col-span-7, .lg\\\\:col-span-8');
          const rightCol = document.querySelector('.lg\\\\:col-span-5, .lg\\\\:col-span-4');
          if (heroCol && rightCol) {
            const r1 = heroCol.getBoundingClientRect();
            const r2 = rightCol.getBoundingClientRect();
            heroOverlap = {
              heroLeft: Math.round(r1.left),
              heroRight: Math.round(r1.right),
              heroWidth: Math.round(r1.width),
              rightLeft: Math.round(r2.left),
              rightRight: Math.round(r2.right),
              isOverlapping: r1.right > r2.left + 1,
              overlapPx: Math.round(r1.right - r2.left)
            };
          }

          // Check internal elements of hero card (e.g. inner div aspect ratio)
          let innerHero = null;
          const liveCardInner = document.querySelector('.aspect-\\\\[16\\\\/10\\\\], .aspect-\\\\[16\\\\/9\\\\]');
          if (liveCardInner) {
            const r = liveCardInner.getBoundingClientRect();
            innerHero = {
              width: Math.round(r.width),
              height: Math.round(r.height),
              right: Math.round(r.right)
            };
          }

          return {
            winW,
            scrollW,
            hasHScroll,
            overflowCount: overflowing.length,
            overflowingTop: overflowing.slice(0, 6),
            heroOverlap,
            innerHero
          };
        })()`,
        returnByValue: true
      });

      console.log(`[${vp.name}] Result:`, JSON.stringify(diag.result.value, null, 2));

      // Screenshot
      const screenshot = await sendPage('Page.captureScreenshot', { format: 'png' });
      const ssPath = `/Users/home/.gemini/antigravity-cli/brain/4e8732cf-2044-461f-ba11-68a2820ee2ba/scratch/${pagePath.replace('/', '')}_${vp.name}.png`;
      fs.writeFileSync(ssPath, Buffer.from(screenshot.data, 'base64'));
    }
  }

  ws.close();
  chrome.kill();
  server.close();
  console.log('\nInspection finished successfully!');
  process.exit(0);
});
