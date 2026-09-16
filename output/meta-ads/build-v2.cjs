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
    <linearGradient id="top" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#070b10" stop-opacity="0.98"/>
      <stop offset="1" stop-color="#101820" stop-opacity="0.92"/>
    </linearGradient>
    <linearGradient id="bottom" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#05080c" stop-opacity="0.98"/>
      <stop offset="1" stop-color="#101820" stop-opacity="0.95"/>
    </linearGradient>
  </defs>
  <rect x="0" y="0" width="1080" height="260" fill="url(#top)"/>
  <rect x="0" y="900" width="1080" height="180" fill="url(#bottom)"/>
  <rect x="0" y="252" width="1080" height="8" fill="#3da5f4"/>
  <rect x="0" y="900" width="1080" height="7" fill="#3da5f4"/>
  <text x="385" y="92" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="56" font-weight="800">TESLA MODEL 3</text>
  <text x="388" y="144" fill="#3da5f4" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="700">DISPONÍVEL PARA TVDE</text>
  <text x="388" y="222" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="66" font-weight="800">325 €</text>
  <text x="590" y="222" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="35" font-weight="700">/ SEMANA</text>
  <rect x="48" y="938" rx="14" ry="14" width="365" height="82" fill="#3da5f4"/>
  <text x="230" y="990" text-anchor="middle" fill="#081018" font-family="Arial, Helvetica, sans-serif" font-size="31" font-weight="800">PEDE INFORMAÇÕES</text>
  <text x="460" y="965" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="27" font-weight="700">256 112 333</text>
  <text x="460" y="1010" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="27" font-weight="700">info@zentrum-tvde.com</text>
</svg>`);

(async () => {
  const invertedLogoSvg = fs.readFileSync(logoSource, 'utf8').replace(
    '</style>',
    'path:not(.cls-2):not(.cls-3):not(.cls-4), polygon { fill: #fff; }</style>'
  );
  const logo = await sharp(Buffer.from(invertedLogoSvg)).resize({ width: 300 }).png().toBuffer();
  for (const [colour, input] of items) {
    const output = path.join(dir, `tesla-model-3-${colour}-meta-square-v2.png`);
    await sharp(path.join(dir, input))
      .resize(1080, 1080, { fit: 'cover' })
      .composite([
        { input: overlay, left: 0, top: 0 },
        { input: logo, left: 50, top: 54, blend: 'over' },
      ])
      .png()
      .toFile(output);
    console.log(output);
  }
})();
