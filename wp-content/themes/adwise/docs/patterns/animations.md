# Pattern: Animations

Default: **vanilla IntersectionObserver** (reveal). GSAP tylko jako escape-hatch dla złożonych timeline'ów.

---

## Reveal — vanilla IntersectionObserver (default)

Globalna klasa `reveal` + jeden IO. Lekkie, zero bundle. Plik `assets/js/reveal.js` enqueue globalnie (lub w view.js bloku).

```js
( function () {
	const els = document.querySelectorAll( '.reveal' );
	if ( ! els.length ) return;

	const reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( reduce ) { els.forEach( ( el ) => el.classList.add( 'is-in' ) ); return; }

	const io = new IntersectionObserver(
		( entries ) => entries.forEach( ( e ) => {
			if ( e.isIntersecting ) { e.target.classList.add( 'is-in' ); io.unobserve( e.target ); }
		} ),
		{ threshold: 0.1, rootMargin: '0px 0px -7% 0px' }
	);

	els.forEach( ( el, i ) => {
		el.style.transitionDelay = Math.min( i, 5 ) * 60 + 'ms'; // stagger
		io.observe( el );
	} );
} )();
```

**CSS:**
```scss
.reveal {
	opacity: 0; transform: translateY(18px);
	transition: opacity .65s cubic-bezier(.22,.61,.36,1),
	            transform .65s cubic-bezier(.22,.61,.36,1);
}
.reveal.is-in { opacity: 1; transform: none; } // transform:none — sticky-safe

@media (prefers-reduced-motion: reduce) {
	.reveal { opacity: 1 !important; transform: none !important; transition: none !important; }
}
```

**Użycie:** redaktor dodaje `customClassName` `reveal` w edytorze, albo dodajesz klasę w render.php bloku.

**Gotcha sticky:** element `position: sticky` z `transform` traci „przyklejenie” podczas animacji. Reveal kończy się `transform: none` → sticky działa po wjeździe. Dlatego nie zostawiaj `transform` w stanie końcowym.

---

## Count-up (liczniki)
Osobny IO z wyższym progiem (`threshold: 0.6` — uruchom gdy licznik dobrze widoczny).

**SEO/no-JS:** render.php wypisuje **finalną wartość** w HTML (Googlebot bez JS ma widzieć „50", nie „0") — JS zeruje dopiero w momencie startu animacji:
```html
<span class="{PREFIX}__num" data-to="50">50</span>
```
```js
const io = new IntersectionObserver( ( es ) => es.forEach( ( e ) => {
	if ( ! e.isIntersecting ) return;
	const el = e.target, to = parseInt( el.dataset.to, 10 );
	let cur = 0; el.textContent = '0'; // zeruj dopiero tu — nie w HTML
	const step = Math.max( 1, Math.round( to / 60 ) );
	const tick = () => { cur = Math.min( to, cur + step ); el.textContent = cur; if ( cur < to ) requestAnimationFrame( tick ); };
	tick(); io.unobserve( el );
} ), { threshold: 0.6 } );
```

---

## GSAP — opcjonalnie (escape-hatch)

Tylko gdy potrzebujesz złożonego timeline / scrub / pin (czego IO nie zrobi). Import w view.js **tego bloku** (nie globalnie):
```js
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
gsap.registerPlugin( ScrollTrigger );
// gsap.timeline({ scrollTrigger: { trigger, scrub: true, pin: true } })…
```
Zawsze respektuj `prefers-reduced-motion` (skip / `gsap.set` final state). Nie ładuj GSAP w blokach którym wystarczy `reveal`.

---

## Lazy-load — enqueue tylko gdy potrzebny

Nie ładuj skryptu animacji (zwłaszcza GSAP ~100KB) na każdej stronie. Wczytaj warunkowo, gdy strona faktycznie używa klasy/bloku (`functions.php`):
```php
add_action( 'wp_enqueue_scripts', function () {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) return;
	$uses = false !== strpos( $post->post_content, 'reveal' );          // klasa reveal w treści
	// lub: $uses = has_block( '{namespace}/slider', $post );
	if ( $uses ) {
		wp_enqueue_script( '{ns}-reveal', get_theme_file_uri( 'build/theme/reveal.js' ), [], null, true );
	}
} );
```
Vanilla `reveal.js` jest lekki — możesz ładować zawsze; warunkowy enqueue ma sens głównie dla GSAP / cięższych view.js.

## Sygnaturowe animacje — czytaj z CSS exportu, NIE z pamięci
Animacji „znaczących" z designu (reveal kafli, wipe, photo-swap, staggered) **NIE odtwarzaj z pamięci** ani nie improwizuj generycznym fade-up — czytaj **dokładny CSS z exportu/Figmy** (keyframes, `transform`, `::after` panel, `--d` stagger, easing, duration) i wdrażaj 1:1. Improwizacja = inny efekt niż design.

**JS skalujący element animowany hoverem → przez CSS var, nie inline `transform`.** Jeśli view.js musi skalować pin/kartę zależnie od zoomu, ustaw tylko CSS var (`el.style.setProperty('--pk', 1/zoom)`); `transform` (z `rotate` + `scale(var(--pk))`) i hover zostają w CSS. Inline `style.transform` nadpisuje regułę CSS → blokuje hover/animację.

## Zasada
- Domyślnie `reveal` (vanilla IO). GSAP gdy IO nie wystarcza.
- ZAWSZE `prefers-reduced-motion: reduce` → wyłącz animacje.
- Stan końcowy reveal: `transform: none` (sticky-safe).
- Parallax/ambient (np. login KV) → `requestAnimationFrame` + lerp, flag reduce-motion.
- Sygnaturowe animacje: 1:1 z CSS exportu. JS skalujący hover-element → CSS var, nie inline transform.
