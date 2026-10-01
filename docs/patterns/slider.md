# Pattern: Slider (Swiper)

Karuzela zdjęć/kart. Zwykle full-width (bez max-width na `__inner`).

---

## Konfiguracja
- Dodaj `"viewScript": "file:./view.js"` do block.json.
- `swiper` jako dependency; import TYLKO w blokach które go używają.

## view.js
```js
import Swiper from 'swiper';
import { Navigation, Thumbs, FreeMode, Autoplay } from 'swiper/modules';

( function () {
	const block = document.querySelector( '.wp-block-{namespace}-{BLOCK_NAME}' );
	if ( ! block ) return;

	const thumbs = new Swiper( block.querySelector( '.{PREFIX}__thumbs' ), {
		modules: [ FreeMode ],
		spaceBetween: 12, slidesPerView: 'auto', freeMode: true, watchSlidesProgress: true,
	} );

	new Swiper( block.querySelector( '.{PREFIX}__main' ), {
		modules: [ Navigation, Thumbs, Autoplay ],
		loop: true, spaceBetween: 0,
		thumbs: { swiper: thumbs },
		navigation: {
			nextEl: block.querySelector( '.{PREFIX}__next' ),
			prevEl: block.querySelector( '.{PREFIX}__prev' ),
		},
	} );
} )();
```

## CSS — GOTCHA importu

Importy CSS Swipera w **`style.scss`**, NIE w view.js. W view.js → slider się rozjeżdża (slidy full-width, Swiper nie zna dostępnej szerokości przy init).

```scss
@use 'swiper/css' as *;
@use 'swiper/css/navigation' as *;
@use 'swiper/css/thumbs' as *;
@use 'swiper/css/free-mode' as *;
```

---

## Edytor — NIE Swiper
Slider w edytorze renderuj jako zwykły grid/lista miniatur z MediaUpload (inline editing slajdów). Swiper inicjalizuje się tylko na froncie (view.js). NIE `ServerSideRender`.

## Konfiguracja (sidebar)
Autoplay delay, slidesPerView, loop → **sidebar** (RangeControl/ToggleControl). Wartości przekaż jako `data-*` na kontenerze, view.js je czyta — NIE hardcoduj w view.js.

```js
// render.php: data-autoplay="<?php echo esc_attr( $delay ); ?>" data-per-view="..."
// view.js: const delay = parseInt( el.dataset.autoplay, 10 );
```

## Display mode toggle (grid ↔ slider)
Gdy blok ma wariant grid/slider: toggle w sidebarze. Edytor pokazuje zawsze widok kanoniczny (grid), slider działa na froncie.
