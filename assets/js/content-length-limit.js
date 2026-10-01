/**
 * Content Length Limit — globalny helper dla block theme.
 *
 * Używany w edit.js bloków do ograniczania długości tekstu w RichText.
 *
 * Mnożniki globalne (ustawiane w functions.php przez wp_add_inline_script):
 *   window.ADWISE.contentLength = {
 *     heading: N,  // ADWISE_CL_HEADING_MULTIPLIER (domyślnie 3)
 *     text:    M,  // ADWISE_CL_TEXT_MULTIPLIER (domyślnie 5)
 *   };
 *
 * Użycie w edit.js:
 *   const h = window.ADWISE.useLengthLimit({
 *     get: () => heading,
 *     set: ( v ) => setAttributes( { heading: v } ),
 *     ref: 'Nagłówek sekcji',
 *     noticeId: 'hero-heading',
 *     type: 'heading',             // multiplier = window.ADWISE.contentLength.heading
 *   });
 *   <RichText value={heading} {...h} placeholder="..." allowedFormats={[]} />
 *
 * Override globalnego mnożnika per pole:
 *   useLengthLimit({ ..., multiplier: 5 })
 *
 * Uwaga edytor-w-iframe (WP 6.3+): selekcja i execCommand muszą operować na
 * dokumencie zdarzenia (e.target.ownerDocument), NIE na oknie rodzica.
 */
( function () {
	window.ADWISE = window.ADWISE || {};

	const hasDeps = !! ( window.wp && window.wp.data && window.wp.notices );
	if ( ! hasDeps ) {
		// eslint-disable-next-line no-console -- dev diagnostyka gdy edytor ładuje helper bez zależności
		console.warn(
			'ADWISE content-length-limit: wp.data / wp.notices niedostępne — limity wyłączone (no-op).'
		);
	}

	const { useDispatch } = hasDeps ? window.wp.data : {};
	const noticesStore = hasDeps ? window.wp.notices.store : null;

	const resolveMultiplier = ( type, explicit ) => {
		if ( typeof explicit === 'number' && explicit > 0 ) {
			return explicit;
		}
		const cfg = window.ADWISE.contentLength || {};
		if ( type === 'heading' ) {
			return Number( cfg.heading ) || 3;
		}
		return Number( cfg.text ) || 5; // 'text' lub domyślny
	};

	// Strip tagów + dekodowanie encji → realna długość widzianego tekstu.
	const stripHtml = ( v ) => {
		const noTags = String( v || '' ).replace( /<[^>]*>/g, '' );
		return noTags
			.replace( /&nbsp;/g, ' ' )
			.replace( /&#0*39;|&apos;/g, "'" )
			.replace( /&quot;/g, '"' )
			.replace( /&lt;/g, '<' )
			.replace( /&gt;/g, '>' )
			.replace( /&amp;/g, '&' );
	};

	/**
	 * React hook — zwraca { onChange, onKeyDown, onPaste } dla RichText.
	 * Bez wp.data/notices zwraca no-op (onChange = passthrough), żeby edit.js
	 * NIGDY nie dostał undefined (inaczej TypeError w renderze = biały ekran edytora).
	 *
	 * @param {Object}   opts              Konfiguracja pola.
	 * @param {Function} opts.get          () => aktualna wartość.
	 * @param {Function} opts.set          ( val ) => setAttributes.
	 * @param {string}   opts.ref          Tekst referencyjny (długość × mnożnik = max).
	 * @param {string}   opts.noticeId     Unikalny id snackbara.
	 * @param {string}   [opts.type]       'heading' | 'text' (domyślnie 'text').
	 * @param {number}   [opts.multiplier] Override globalnego mnożnika.
	 * @return {Object} Handlery { onChange, onKeyDown, onPaste }.
	 */
	function useLengthLimit( opts ) {
		const { get, set, ref, noticeId, type = 'text' } = opts;

		if ( ! hasDeps ) {
			return { onChange: set, onKeyDown: () => {}, onPaste: () => {} };
		}

		const multiplier = resolveMultiplier( type, opts.multiplier );
		// Dolna granica: krótki ref nie może zablokować pola całkowicie.
		const max = Math.max( 40, ( ref?.length || 0 ) * multiplier );

		// eslint-disable-next-line react-hooks/rules-of-hooks -- hasDeps to stała modułowa: ścieżka hooków deterministyczna per środowisko, kolejność się nie zmienia między renderami
		const { createWarningNotice } = useDispatch( noticesStore );
		const warn = () =>
			createWarningNotice( `Tekst za długi — maks. ${ max } znaków`, {
				type: 'snackbar',
				id: noticeId,
			} );

		return {
			// Przekroczenie → NIE zapisuj (zostaw poprzednią wartość). NIE tnij na
			// stripHtml — to kasowało bold/linki i rozcinało emoji.
			onChange: ( val ) => {
				if ( stripHtml( val ).length <= max ) {
					set( val );
				} else {
					warn();
				}
			},

			onKeyDown: ( e ) => {
				if ( e.ctrlKey || e.metaKey ) {
					return;
				}
				if ( e.key.length !== 1 ) {
					return;
				}
				// Selekcja z dokumentu zdarzenia (iframe edytora), nie z okna rodzica.
				const win = e.target.ownerDocument.defaultView;
				const sel = win.getSelection();
				if ( sel && ! sel.isCollapsed ) {
					return;
				} // zamiana zaznaczenia nie zwiększa długości
				if ( stripHtml( get() ).length >= max ) {
					e.preventDefault();
					warn();
				}
			},

			// Gdy wklejenie przekroczyłoby limit → blokuj całość + ostrzeż (user skróci).
			// Nie wstawiamy przyciętego przez execCommand — w iframe zawodzi i gubi treść.
			onPaste: ( e ) => {
				const available = max - stripHtml( get() ).length;
				const pasted =
					e.clipboardData ||
					e.target.ownerDocument.defaultView.clipboardData;
				const text = pasted ? pasted.getData( 'text/plain' ) : '';
				if ( text.length > available ) {
					e.preventDefault();
					warn();
				}
			},
		};
	}

	window.ADWISE.useLengthLimit = useLengthLimit;
} )();
