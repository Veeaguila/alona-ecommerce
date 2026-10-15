/**
 * ISO/IEC 15417 Code 128 (Subset B) SVG Barcode Generator
 * Generates crisp vector SVG barcodes for shipping labels and waybills.
 */

const CODE128_PATTERNS = [
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
    '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
    '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
    '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
    '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
    '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
    '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
    '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
    '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
];

const START_B = 104;
const STOP = 106;

/**
 * Encodes text into Code 128B bar widths and produces SVG rectangles.
 * 
 * @param {string} text The text to encode into the barcode
 * @param {object} options Height, narrow bar width, and display settings
 * @returns {object} { svgHtml, totalWidth, height }
 */
export function generateBarcodeSvg(text, options = {}) {
    const raw = String(text ?? 'ITEM-0').trim();
    const safeText = raw.length > 0 ? raw : 'ITEM-0';
    const barHeight = options.height || 60;
    const moduleWidth = options.moduleWidth || 2;
    const quietZone = options.quietZone !== undefined ? options.quietZone : 10;

    const symbols = [START_B];
    let checksum = START_B;

    for (let i = 0; i < safeText.length; i++) {
        const code = safeText.charCodeAt(i);
        // Code 128B covers ASCII 32 to 127
        const val = (code >= 32 && code <= 127) ? (code - 32) : 0;
        symbols.push(val);
        checksum += (i + 1) * val;
    }

    checksum = checksum % 103;
    symbols.push(checksum);
    symbols.push(STOP);

    // Calculate modules & draw bars
    let currentX = quietZone;
    const rects = [];

    for (const sym of symbols) {
        const pattern = CODE128_PATTERNS[sym] || CODE128_PATTERNS[0];
        let isBar = true;

        for (let j = 0; j < pattern.length; j++) {
            const width = parseInt(pattern[j], 10) * moduleWidth;
            if (isBar) {
                rects.push(`<rect x="${currentX}" y="0" width="${width}" height="${barHeight}" fill="#000000" />`);
            }
            currentX += width;
            isBar = !isBar;
        }
    }

    currentX += quietZone;
    const totalWidth = currentX;

    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${totalWidth} ${barHeight}" width="100%" height="${barHeight}" preserveAspectRatio="xMidYMid meet">
            <rect width="100%" height="100%" fill="#ffffff" />
            ${rects.join('')}
        </svg>
    `.trim();

    return {
        svg,
        totalWidth,
        height: barHeight,
        text: safeText
    };
}

