import { c as createComponent } from './astro-component_BTi_JTrv.mjs';
import 'piccolore';
import { o as renderComponent, k as renderTemplate } from './entrypoint_CgkU1_RO.mjs';

const prerender = false;
const $$KeystaticAstroPage = createComponent(($$result, $$props, $$slots) => {
  return renderTemplate`${renderComponent($$result, "Keystatic", null, { "client:only": "react", "client:component-hydration": "only", "client:component-path": "/private/tmp/mclair/node_modules/@keystatic/astro/internal/keystatic-page.js", "client:component-export": "Keystatic" })}`;
}, "/private/tmp/mclair/node_modules/@keystatic/astro/internal/keystatic-astro-page.astro", void 0);

const $$file = "/private/tmp/mclair/node_modules/@keystatic/astro/internal/keystatic-astro-page.astro";
const $$url = undefined;

const _page = /*#__PURE__*/Object.freeze(/*#__PURE__*/Object.defineProperty({
	__proto__: null,
	default: $$KeystaticAstroPage,
	file: $$file,
	prerender,
	url: $$url
}, Symbol.toStringTag, { value: 'Module' }));

const page = () => _page;

export { page };
