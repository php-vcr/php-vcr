// @ts-check
import {themes as prismThemes} from 'prism-react-renderer';

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'php-vcr',
  tagline: "Record your test suite's HTTP interactions and replay them on every run after that",
  favicon: 'img/favicon-32.png',

  future: {
    v4: true,
  },

  url: 'https://php-vcr.github.io',
  baseUrl: '/php-vcr/',

  organizationName: 'php-vcr',
  projectName: 'php-vcr',

  onBrokenLinks: 'throw',

  markdown: {
    format: 'detect',
  },

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          path: '../docs',
          routeBasePath: '/',
          sidebarPath: './sidebars.js',
          editUrl: 'https://github.com/php-vcr/php-vcr/edit/master/docs/',
          showLastUpdateTime: true,
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  themes: [
    [
      '@easyops-cn/docusaurus-search-local',
      /** @type {import('@easyops-cn/docusaurus-search-local').PluginOptions} */
      ({
        hashed: true,
        indexBlog: false,
        indexPages: false,
        docsRouteBasePath: '/',
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      colorMode: {
        respectPrefersColorScheme: true,
      },
      navbar: {
        title: 'php-vcr',
        logo: {
          alt: 'php-vcr logo',
          src: 'img/php-vcr-logo.png',
        },
        items: [
          {
            type: 'docsVersionDropdown',
            position: 'right',
          },
          {
            href: 'https://github.com/php-vcr/php-vcr',
            label: 'GitHub',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              {label: 'Getting Started', to: '/getting-started'},
              {label: 'Upgrading', to: '/upgrading'},
            ],
          },
          {
            title: 'Community',
            items: [
              {label: 'GitHub Issues', href: 'https://github.com/php-vcr/php-vcr/issues'},
              {label: 'Packagist', href: 'https://packagist.org/packages/php-vcr/php-vcr'},
            ],
          },
          {
            title: 'More',
            items: [
              {label: 'GitHub', href: 'https://github.com/php-vcr/php-vcr'},
              {label: 'License (MIT)', href: 'https://github.com/php-vcr/php-vcr/blob/master/LICENSE.md'},
            ],
          },
        ],
        copyright: `Copyright © ${new Date().getFullYear()} php-vcr contributors.`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
      },
    }),
};

export default config;
