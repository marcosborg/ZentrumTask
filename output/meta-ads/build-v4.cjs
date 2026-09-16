const sharp = require('sharp');
const path = require('path');
const fs = require('fs');

const dir = __dirname;
const logoSource = 'C:/Users/Marcos Borges/Desktop/logo.svg';
const items = [
  ['branco', 'base-branco-v2.png'],
  ['vermelho', 'base-vermelho-v2.png'],
  ['cinzento', 'base-cinzento-v2.png'],
];

const overlay = Buffer.from(`
<svg width="1080" height="1080" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="shade" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#05090e" stop-opacity=".98"/>
      <stop offset=".52" stop-color="#05090e" stop-opacity=".06"/>
      <stop offset="1" stop-color="#05090e" stop-opacity=".82"/>
    </linearGradient>
    <linearGradient id="blue" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#188ee8" stop-opacity=".96"/>
      <stop offset="1" stop-color="#51b7ff" stop-opacity=".88"/>
    </linearGradient>
    <filter id="shadow"><feDropShadow dx="0" dy="14" stdDeviation="16" flood-opacity=".38"/></filter>
  </defs>

  <rect width="1080" height="1080" fill="url(#shade)"/>

  <!-- Graphics overlap one another only inside the empty upper area -->
  <polygon points="0,166 598,166 532,355 0,388" fill="url(#blue)"/>
  <polygon points="566,166 598,166 532,355 502,360" fill="#ffffff" fill-opacity=".72"/>

  <text x="54" y="218" fill="#071018" font-family="Segoe UI, Arial, sans-serif" font-size="23" font-weight="800" letter-spacing="3">A TUA PRÓXIMA</text>
  <text x="52" y="275" fill="#ffffff" font-family="Arial Black, Segoe UI, sans-serif" font-size="55" font-weight="900">VIATURA TVDE</text>
  <text x="54" y="320" fill="#071018" font-family="Segoe UI, Arial, sans-serif" font-size="25" font-weight="800">TESLA MODEL 3  •  DISPONÍVEL</text>

  <!-- Price overlaps the blue panel, never the vehicle -->
  <g filter="url(#shadow)">
    <polygon points="540,204 1034,184 1004,350 510,372" fill="#071018" fill-opacity=".96"/>
    <polygon points="540,204 1034,184 1030,208 536,228" fill="#ffffff"/>
    <text x="570" y="304" fill="#ffffff" font-family="Arial Black, Segoe UI, sans-serif" font-size="83" font-weight="900">325 €</text>
    <text x="835" y="303" fill="#48adf8" font-family="Segoe UI, Arial, sans-serif" font-size="25" font-weight="800">/ SEMANA</text>
  </g>

  <!-- CTA occupies floor/background only -->
  <g filter="url(#shadow)">
    <polygon points="620,918 1080,882 1080,1032 588,1032" fill="#071018" fill-opacity=".94"/>
    <polygon points="620,918 1080,882 1080,906 614,943" fill="#48adf8"/>
    <text x="640" y="985" fill="#ffffff" font-family="Arial Black, Segoe UI, sans-serif" font-size="31" font-weight="900">PEDE INFORMAÇÕES</text>
  </g>

  <text x="54" y="974" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="28" font-weight="800">256 112 333</text>
  <text x="54" y="1018" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="25" font-weight="600">info@zentrum-tvde.com</text>
</svg>`);

(async () => {
  const invertedLogoSvg = fs.readFileSync(logoSource, 'utf8').replace(
    '</style>',
    'path:not(.cls-2):not(.cls-3):not(.cls-4) { fill: #fff; } polygon { fill: #071018; }</style>'
  );
  const logo = await sharp(Buffer.from(invertedLogoSvg)).resize({ width: 250 }).png().toBuffer();

  for (const [colour, input] of items) {
    const output = path.join(dir, `tesla-model-3-${colour}-meta-square-v4.png`);
    await sharp(path.join(dir, input))
      .resize(1080, 1080, { fit: 'cover' })
      .composite([
        { input: overlay, left: 0, top: 0 },
        { input: logo, left: 54, top: 48, blend: 'over' },
      ])
      .png()
      .toFile(output);
    console.log(output);
  }
})();
