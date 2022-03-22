

(function () {

	const $debugBar = document.getElementsByClassName('DebugBar')[0];
	// $debugBar.style.display = 'block'

	// ------------------------------------------------------------------------- TABS

	const selectedTabKey = '_bowlDebugBar_selectedTab';
	const $tabButtons = [...document.getElementsByClassName('DebugBar_tabButton')];
	const $tabContents = [...document.getElementsByClassName('DebugBar_tabContent')];

	function initTabs () {
		$tabButtons.map( ($button, index) => {
			$button.addEventListener('click', event => {
				event.preventDefault();
				updateCurrentTabState( index );
			})
		})

		const tab = localStorage.getItem(selectedTabKey)
		updateCurrentTabState( tab != null ? tab : 0 );
	}

	function updateCurrentTabState (index) {
		localStorage.setItem(selectedTabKey, index);
		$tabButtons.map( ($button, i) => {
			$button.classList.toggle('selected', index == i) // no strick check on purpose
		});
		$tabContents.map( ($content, i) => {
			$content.classList.toggle('selected', index == i)
		})
	}

	// ------------------------------------------------------------------------- RESIZE

	const resizeKey = '_bowlDebugBar_size';
	const $resizeButton = document.getElementsByClassName('DebugBar_resizeButton')[0];
	const height = localStorage.getItem(resizeKey);
	let currentHeight = parseInt(height != null ? height : '400');
	let isResizing = false;
	function setResizingState ( newState ) {
		isResizing = newState
		$debugBar.classList.toggle('DebugBar-resizing', isResizing );
	}
	function initResize () {
		let mousePosition;
		function updateHeightState () {
			$tabContents.map( $content => {
				$content.style.height = currentHeight + 'px';
			})
		}
		function mouseDown ( event ) {
			event.preventDefault();
			mousePosition = event.screenY;
			setResizingState( true )
			setLockedState( isLocked )
			document.addEventListener('mousemove', mouseMove)
			document.addEventListener('mouseup', mouseUp);
		}
		function mouseMove ( event ) {
			event.preventDefault();
			const newMousePosition = event.screenY;
			const moveDelta = mousePosition - newMousePosition;
			mousePosition = newMousePosition;
			currentHeight += moveDelta;
			updateHeightState();
			setLockedState( isLocked )
			localStorage.setItem(resizeKey, currentHeight);
		}
		function mouseUp ( event ) {
			event.preventDefault();
			setLockedState( isLocked )
			setResizingState( false )
			document.removeEventListener('mouseup', mouseUp);
			document.removeEventListener('mousemove', mouseMove)
		}
		$resizeButton.addEventListener('mousedown', mouseDown)
		updateHeightState();
	}

	// ------------------------------------------------------------------------- LOCK

	const lockKey = '_bowlDebugBar_locked';
	const $lockButton = document.getElementsByClassName('DebugBar_lockButton')[0];
	let isLocked = localStorage.getItem(lockKey) === 'true';

	function initLock () {
		$lockButton.addEventListener('click', event => {
			event.preventDefault();
			setLockedState( !isLocked );
		});
		setLockedState( isLocked );
	}

	function setLockedState ( newLockState ) {
		isLocked = newLockState;
		localStorage.setItem(lockKey, isLocked ? 'true' : 'false');
		$debugBar.classList.toggle('DebugBar-locked', isLocked );
		document.documentElement.style.paddingBottom = isLocked ? currentHeight + 'px' : null;
	}

	// ------------------------------------------------------------------------- INIT

	initTabs();
	initResize();
	initLock();
})()