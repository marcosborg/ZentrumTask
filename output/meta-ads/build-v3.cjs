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

  <!-- editorial blue plane crossing the image -->
  <polygon points="0,180 650,180 548,470 0,540" fill="url(#blue)"/>
  <polygon points="610,180 650,180 548,470 514,475" fill="#ffffff" fill-opacity=".75"/>

  <text x="54" y="242" fill="#071018" font-family="Segoe UI, Arial, sans-serif" font-size="27" font-weight="800" letter-spacing="3">A TUA PRÓXIMA</text>
  <text x="52" y="306" fill="#ffffff" font-family="Arial Black, Segoe UI, sans-serif" font-size="62" font-weight="900">VIATURA TVDE</text>
  <text x="54" y="352" fill="#071018" font-family="Segoe UI, Arial, sans-serif" font-size="27" font-weight="800" letter-spacing="1">TESLA MODEL 3  •  DISPONÍVEL</text>

  <!-- price card overlaps graphic and vehicle -->
  <g filter="url(#shadow)">
    <polygon points="52,402 530,370 573,524 93,555" fill="#071018" fill-opacity=".95"/>
    <polygon points="52,402 530,370 536,392 58,425" fill="#ffffff"/>
    <text x="95" y="500" fill="#ffffff" font-family="Arial Black, Segoe UI, sans-serif" font-size="94" font-weight="900">325 €</text>
    <text x="385" y="499" fill="#48adf8" font-family="Segoe UI, Arial, sans-serif" font-size="28" font-weight="800">/ SEMANA</text>
  </g>

  <!-- campaign CTA floating over the photograph -->
  <g filter="url(#shadow)">
    <polygon points="510,888 1080,842 1080,1026 468,1026" fill="#071018" fill-opacity=".94"/>
    <polygon points="510,888 1080,842 1080,866 504,913" fill="#48adf8"/>
    <text x="545" y="953" fill="#ffffff" font-family="Arial Black, Segoe UI, sans-serif" font-size="39" font-weight="900">COMEÇA JÁ</text>
    <text x="545" y="997" fill="#48adf8" font-family="Segoe UI, Arial, sans-serif" font-size="27" font-weight="800">PEDE INFORMAÇÕES  →</text>
  </g>

  <!-- contacts sit independently, not in a footer box -->
  <text x="54" y="976" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="28" font-weight="800">256 112 333</text>
  <text x="54" y="1018" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="25" font-weight="600">info@zentrum-tvde.com</text>
</svg>`);

(async () => {
  const invertedLogoSvg = fs.readFileSync(logoSource, 'utf8').replace(
    '</style>',
    'path:not(.cls-2):not(.cls-3):not(.cls-4) { fill: #fff; } polygon { fill: #071018; }</style>'
  );
  const logo = await sharp(Buffer.from(invertedLogoSvg)).resize({ width: 250 }).png().toBuffer();

  for (const [colour, input] of items) {
    const output = path.join(dir, `tesla-model-3-${colour}-meta-square-v3.png`);
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
