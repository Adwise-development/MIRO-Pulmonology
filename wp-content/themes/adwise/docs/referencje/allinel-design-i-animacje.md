# Allinel — język wizualny i animacje („obwód”)

Analiza z 22.09.2026: strona główna produkcji (Elementor, post 9 + szablony 883 stopka, 304 menu mobilne)
oraz makieta „Sklep stacjonarny” (`design/sklep/`). **Czytaj przed planem każdego bloku z liniami, ⊗, ramką lub poświatą.**
Oryginalny kod z Elementora: `design/homepage-animacje/` (CSS + SVG 1:1, selektor Elementora podmieniony na `.elementor-element-ID`).

---

## 1. Idea: strona jako obwód elektryczny

Wszystkie „dziwne paski” to jedna metafora: **przewody (cienkie linie) → prąd (biegnąca iskra) → odbiorniki (⊗ żarówki),
które zapalają się limonką, gdy prąd dojdzie**. Ramka hero ma **wyłącznik** — dźwignia opada, obwód się zamyka,
ramka i żarówki świecą. Nowe bloki mają mówić tym samym językiem, nie wymyślać nowych efektów.

Stan „zgaszony” = szary `#7D90A1` (linie, obrysy, ⊗). Stan „świeci” = limonka `#DEFB2E` + poświata
`rgba(222,251,46,.35–.6)`. Tło ciemnych sekcji `#192E3C`, jasnych `#F2F4F6`.

**Takt:** strona główna pracuje w pętli **3 s** (ramka, iskry SVG, pulsowanie ⊗), linia w stopce **10 s** (8 s na laptopie),
makieta: kafle hero **4,5 s**, sekwencja kroków doradztwa **6 s** z przesunięciem co 1,5 s (`--op`).

---

## 2. Katalog efektów

### A. Ramka z wyłącznikiem (hero strony głównej) — `hero-ramka.css`
- Kontener z `border: 2px solid #7D90A1`, `animation: close-border 3s infinite` → 40–60% pętli ramka limonkowa.
- Na dole dwa absolutne dividery: `.wire-one` = **dźwignia** (`close-current`: `rotate(-10deg)` → `0deg` w 39–61%,
  to ten ukośny „ząbek” w dolnej krawędzi), `.wire-fake` = stały odcinek.
- `.wire-buld` (SVG z JetElements, prawy bok) = **żarówki ⊗**: `rect` fill `#192E3C`→`#DEFB2E` (`close-buld-rect`),
  `path/g` stroke odwrotnie (`close-buld`), zsynchronizowane z ramką.
- Wniosek dla bloków: ramka = `border` + jedna animacja koloru; dźwignia = pseudo-element z `transform-origin` na zawiasie.

### B. Przewód z iskrą (SVGator) — `svg-*.html` + `svg-*.css`
- 7 widgetów HTML z SVG eksportowanym z **SVGatora** (id `eXXXXXXXXXXX1…`, klatki `*_to__to`, `*_ts__ts`, `*_tr__tr`,
  `*_f_p`, `*_c_o`). Czysty CSS, zero JS.
- Linia: `path` ze `stroke` gradientem limonka → przezroczystość, zakręty łukami (r ≈ 24).
- **Iskra**: grupa 3 elips z `radialGradient` (limonka → granat → 0) + `feGaussianBlur stdDeviation 5`,
  jedzie po `offset-path: path('…')` (`offset-distance 0 → 100%`, 3000 ms linear infinite), skalowana i wygaszana
  (`opacity 0 → 1` w pierwszych 20%).
- Węzeł ⊗ na trasie: `fill` błyska `#DEFB2E` w 90–96% pętli (gdy iskra przejeżdża); krzyżyki ⊗ obracają się (`*_tr`).
- Pozycjonowanie w Elementorze: widget `position:absolute; width:1440px; left:50%; translateX(-50%)`, `bottom` ujemny
  (np. −440 px), osobne wartości dla ≤1366 / ≤1023 / ≤767. Pary desktop/mobile (`svg-2a/2b`, `svg-3a/3b`) przełączane
  widocznością Elementora.
- Uwaga: id w SVG są globalne w dokumencie — dwa egzemplarze tego samego SVG na stronie psują gradienty/filtry.

### C. Pulsujący węzeł ⊗ (karty usług, rząd atutów) — `pulsowanie-*.css`
- `svg g rect { animation: pulsate 3s infinite }` (fill biały → limonka w 55%) +
  `svg { animation: pulsate-svg 3s infinite }` (`drop-shadow(0 0 32px rgba(222,251,46,.6))` w 55%).
- Kolejne ikony z `animation-delay` 0 / 0,3 s → fala wzdłuż przewodu.

### D. Poświata jadąca po linii (stopka, linia nad formularzem) — `stopka-linia-*.css`
- Linia 1 px: `linear-gradient(90deg, transparent, #FFF 50%, transparent)`, `opacity .3`.
- `::before` 537×48 i `::after` 415×106 (blur 5px) z `radial-gradient(rgba(222,251,46,.5) 0, transparent 58.5%)`,
  animacja `left` 0 → `calc(100% − 537px)` → 0, 10 s, `cubic-bezier(1,0,.605,1)`.
- Do poprawy w naszej wersji: animować `transform: translateX()`, nie `left` (layout co klatkę).

### E. Migocząca ramka (menu mobilne, popup 304) — `menu-mobilne-ramka-*.css`
- `border-color` szary ↔ limonka ze skokami (10.1%/10.2%, 30%/30.1%) — efekt „iskrzenia styku”, 3 s linear.

### F. Makieta „Sklep” — rozwinięcie tego samego języka (`design/sklep/Sklep2.css`, `Sklep2.js`)
| Efekt | Gdzie | Mechanika |
|---|---|---|
| `przewaga-swiatlo` 4,5 s | kafle hero | obrys `--gray` → `--lime` + `box-shadow 0 0 24px rgba(lime,.35)` w 9–26%; `::after` — limonkowy „wtyk” 28×2 px z prawego boku karty |
| `dor-*` 6 s, `--op` 0/1,5/3/4,5 s | 4 kroki doradztwa | ⊗ po kolei: tło `#F2F4F6`→lime, znak `#7D90A1`→`#192E3C`, obwód stroke →lime, karta `inset 0 0 0 2px lime` (7–20%) |
| przewód liczony w JS | doradztwo, montaż | `svg.*__linie` na całą sekcję, `path d` budowany z `getBoundingClientRect()` elementów (poziomo → łuk r 24 → pion x 1402 lub x 68 → do boksu); przeliczany na `load`, `resize`, `document.fonts.ready` |
| siatka tła | doradztwo | pionowe linie x 78 i 1363, gradient pionowy, `opacity .45` (to samo „rusztowanie” co na stronie głównej) |
| paralaksa | pas zdjęcia pod sekcją 02 | `translate3d` ± połowa zapasu wysokości, rAF, wyłączona przy `prefers-reduced-motion` |
| „tasowanie” sekcji | produkty → salon → hurtownia | sekcja spodnia `position:sticky; top = min(0, vh − wysokość)`, następna wjeżdża nad nią; `--zakrycie` 0–1 do przyciemnienia |
| akordeon FAQ | FAQ | jedno otwarte naraz, wysokość z `firstElementChild.offsetHeight` (nie `scrollHeight` — skok o 25 px), wjazd `faq-wjazd .5s` |

Makieta ma `prefers-reduced-motion` dla każdego efektu — **stan statyczny = pierwszy węzeł zapalony**, reszta zgaszona.

---

## 3. Zasady dla bloków wtyczki `allinel-lp`

1. **Wspólna warstwa „obwód” we wtyczce** (`assets/scss/obwod.scss` + `assets/js/obwod.js` → `build/theme/obwod.*`):
   klatki i klasy użytkowe raz dla całej strony, bloki tylko ich używają. Nie kopiuj `@keyframes` do każdego bloku.
2. **Jeden takt**: `--al-takt: 3s` (ramka, iskra, ⊗), sekwencje przez `--op` (opóźnienie) — nie wymyślaj nowych czasów.
3. **Tylko tanie właściwości w pętlach nieskończonych**: `transform`, `opacity`, `offset-distance`, `fill/stroke` małych SVG.
   `box-shadow`, `filter: drop-shadow`, `left` — tylko na kilku elementach naraz (na stronie głównej chodzi 33 animacji bez przerwy).
4. **Pauza poza ekranem**: `obwod.js` dokłada `.is-anim` sekcjom w widoku (IntersectionObserver), CSS animuje tylko
   `.is-anim …` — reszta stoi (`animation-play-state: paused`).
5. **`prefers-reduced-motion: reduce`** → zero ruchu, stan statyczny jak w makiecie (pierwszy ⊗ zapalony, linie szare).
6. **Przewody zależne od układu** (doradztwo, montaż) rysuje JS z położeń elementów — `render.php` daje pusty `<svg>`
   z `aria-hidden="true"`, JS liczy `d`. Na ≤1024 px przewód przebudować albo ukryć (makieta nie ma mobile — decyzja w planie bloku).
7. **SVG ze SVGatora** można użyć 1:1 (plik w `assets/`), ale id prefiksować per egzemplarz (`wp_unique_id()` w `render.php`)
   i usuwać atrybuty SVGatora (`project-id`, `export-id`, `cached`).
8. Dekoracje (linie, ⊗, poświaty) są `aria-hidden="true"` i `pointer-events: none`; nie wpływają na wysokość sekcji
   (absolutne względem sekcji z `overflow: clip` w osi X — patrz pamięć „Reveal translateX”: `overflow-x: clip`, nie `hidden`).
9. Nagłówek produkcji (Elementor) jest przezroczysty i nachodzi na treść — pierwszy blok ma górny odstęp pod nagłówek.

---

## 4. System wizualny (Elementor kit 8 = makieta)

- **Kontener 1288 px** = 1440 − 2 × 76 px (makieta: `padding: 0 76px`). W blokach: `__inner` max-width 1440 + padding boczny `gutter`.
- **Breakpointy produkcji:** mobile ≤767, tablet ≤1024, laptop ≤1366. Bloki: layout @1024, drobne korekty @767 (CLAUDE.md).
- **Typografia** (Space Grotesk; desktop / tablet / mobile): H1 48/36/34 · 700 · 110% — H2 40/32/28 · 700 · 115% —
  H3 32/26/24 · 500 · 120% — H4 24/22/20 · 500 · 125% — Body 20/—/18 · 400 · 140% — Body 2 16/—/14 · 150% —
  Small 12 · 150% — Button 16/—/14 · 500 · 130% — Link 14 · 130%. Makieta używa dokładnie tej skali (+ 18 px ×3).
- **Kolory kitu:** `#FFFFFF`, `#192E3C`, `#7D90A1`, `#DEFB2E`, `#374B5A`, `#F2F4F6`, `#CFD6DC`, `#738697`, `#DBE0E5`, `#213644`.
  Makieta dokłada: `#506473` (linia), `#CBD3D9` (pasek), `#F4EDE4`/`#9A7B5F` (piasek — salon), `#040401`.
  Mapowanie na slugi: `theme.json` wtyczki + `project.md` §4.
