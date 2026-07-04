/**
 * QR Code Helper Functions
 * Provides adaptive QR code generation based on URL length
 */

/**
 * Get appropriate QR correct level based on URL length
 * @param {string} url - The URL to encode
 * @returns {number} QRCode.CorrectLevel value
 */
function getQRCorrectLevel(url) {
    if (!url) return QRCode.CorrectLevel.H;
    var len = url.length;
    if (len <= 200) return QRCode.CorrectLevel.H;
    if (len <= 500) return QRCode.CorrectLevel.Q;
    if (len <= 1000) return QRCode.CorrectLevel.M;
    return QRCode.CorrectLevel.L;
}

/**
 * Get appropriate QR size based on URL length
 * @param {string} url - The URL to encode
 * @param {number} defaultSize - Default QR code size
 * @returns {number} Recommended QR code size
 */
function getQRSize(url, defaultSize) {
    if (!url) return defaultSize;
    var len = url.length;
    if (len <= 500) return defaultSize;
    if (len <= 1000) return Math.round(defaultSize * 1.15);
    return Math.round(defaultSize * 1.25);
}
