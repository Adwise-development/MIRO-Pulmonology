/**
 * Adwise — Custom Login KV: floating squares + parallax.
 * Enqueue: inc/adwise-login.php → login_enqueue_scripts (footer).
 */
( function () {
	const wrap = document.getElementById( 'adw-squares' );
	const panel = wrap && wrap.parentElement;
	if ( ! wrap || ! panel ) {
		return;
	}

	const N = 14;
	const squares = [];
	for ( let i = 0; i < N; i++ ) {
		const s = document.createElement( 'div' );
		s.className = 'adw-brand__sq';
		const size = 60 + Math.random() * 220;
		const rot = Math.random() * 24 - 12;
		s.style.width = s.style.height = size + 'px';
		s.style.left = Math.random() * 110 - 5 + '%';
		s.style.top = Math.random() * 110 - 5 + '%';
		s.style.opacity = ( 0.05 + Math.random() * 0.18 ).toFixed( 3 );
		s.style.transform = 'rotate(' + rot + 'deg)';
		s._depth = 8 + Math.random() * 38;
		s._rot = rot;
		s._phase = Math.random() * Math.PI * 2;
		wrap.appendChild( s );
		squares.push( s );
	}

	// prefers-reduced-motion → zostaw kwadraty statyczne: bez mousemove, bez rAF.
	const reduced =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( reduced ) {
		return;
	}

	let tx = 0,
		ty = 0,
		cx = 0,
		cy = 0;
	// Cache rect (odczyt w mousemove wymuszałby reflow co ruch); odśwież na resize.
	let rect = panel.getBoundingClientRect();
	window.addEventListener( 'resize', function () {
		rect = panel.getBoundingClientRect();
	} );

	panel.addEventListener( 'mousemove', function ( e ) {
		cx = ( e.clientX - rect.left ) / rect.width - 0.5;
		cy = ( e.clientY - rect.top ) / rect.height - 0.5;
	} );
	panel.addEventListener( 'mouseleave', function () {
		cx = 0;
		cy = 0;
	} );

	function tick( now ) {
		tx += ( cx - tx ) * 0.07;
		ty += ( cy - ty ) * 0.07;
		for ( let i = 0; i < squares.length; i++ ) {
			const s = squares[ i ];
			const amb = Math.sin( now / 2400 + s._phase ) * 14;
			const amx = Math.cos( now / 2800 + s._phase ) * 9;
			s.style.transform =
				'translate(' +
				( tx * s._depth + amx ).toFixed( 2 ) +
				'px,' +
				( ty * s._depth + amb ).toFixed( 2 ) +
				'px) rotate(' +
				s._rot +
				'deg)';
		}
		window.requestAnimationFrame( tick );
	}
	window.requestAnimationFrame( tick );
} )();
