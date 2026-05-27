
export default {
  bootstrap: () => import('./main.server.mjs').then(m => m.default),
  inlineCriticalCss: true,
  baseHref: '/',
  locale: undefined,
  routes: [
  {
    "renderMode": 2,
    "route": "/"
  }
],
  entryPointToBrowserMapping: undefined,
  assets: {
    'index.csr.html': {size: 6648, hash: 'f39f5b249aa6132eda8be0ea547c2a6135b91fe51f793242a2d84393142beee6', text: () => import('./assets-chunks/index_csr_html.mjs').then(m => m.default)},
    'index.server.html': {size: 1295, hash: '6696aace84968efd5e3170542f8ec30d89cd505e02e2b27f2fd681f270deb343', text: () => import('./assets-chunks/index_server_html.mjs').then(m => m.default)},
    'index.html': {size: 48013, hash: '745cc384ffde56c7355307e244439bbcd09ead170e76a5aa5a94ddc38d778254', text: () => import('./assets-chunks/index_html.mjs').then(m => m.default)},
    'styles-UZW4Z5KM.css': {size: 29102, hash: '+Sjwj28Smfk', text: () => import('./assets-chunks/styles-UZW4Z5KM_css.mjs').then(m => m.default)}
  },
};
