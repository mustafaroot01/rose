/**
 * plugins/webfontloader.js
 *
 * webfontloader documentation: https://github.com/typekit/webfontloader
 */

export async function loadFonts() {
  // خط الواجهة "Thmanyah Sans" يُحمَّل محلياً عبر @font-face في resources/styles/styles.scss
  // لم نعد نحمّل خط Google (Public Sans).
}

export default function () {
  loadFonts()
}
