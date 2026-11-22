/* global libphonenumber, dcibAdmin */
(function () {
	const form = document.getElementById( "dcib-form" );
	if ( ! form ) {
		return;
	}

	let formChanged = false;

	function createToast( message ) {
		const toast = document.createElement( "div" );
		toast.className = "dcib-toast";
		toast.textContent = message;
		document.body.appendChild( toast );

		setTimeout( () => {
			toast.classList.add( "show" );
			setTimeout( () => {
				toast.classList.remove( "show" );
				setTimeout( () => toast.remove(), 300 );
			}, 2000 );
		}, 100 );
	}

	async function copyToClipboard( text ) {
		try {
			await navigator.clipboard.writeText( text );
			createToast( dcibAdmin.copiedToClipboard );
		} catch ( error ) {
			window.console.error( "Error copying text:", error );
		}
	}

	function enforceParentheses( input ) {
		const cursorPosition = input.selectionStart;
		const originalValue = input.value;
		const cleanValue = originalValue.replace( /[^a-zA-Z0-9_]/g, "" );
		const finalValue = `(${ cleanValue.toUpperCase() })`;

		input.value = finalValue;

		const prefix = originalValue.slice( 0, cursorPosition );
		const cleanBeforeCursor = ( prefix.match( /[a-zA-Z0-9_]/g ) || [] ).length;
		const newPosition = 1 + cleanBeforeCursor;

		input.setSelectionRange( newPosition, newPosition );
	}

	function validatePhoneField( input ) {
		const value = input.value.trim();
		const indicator = input.parentElement.querySelector( ".dcib-phone-validation" );

		if ( ! value ) {
			if ( indicator ) {
				indicator.textContent = "";
				indicator.className = "dcib-phone-validation";
			}
			input.style.borderColor = "";
			return;
		}

		let isValid = false;
		if ( typeof libphonenumber !== "undefined" ) {
			try {
				const phoneNumber = libphonenumber.parsePhoneNumber( value, dcibAdmin.defaultCountry );
				isValid = phoneNumber && phoneNumber.isValid();
			} catch ( e ) {
				isValid = false;
			}
		}

		if ( ! indicator ) {
			const el = document.createElement( "span" );
			el.className = "dcib-phone-validation";
			input.parentElement.appendChild( el );
		}

		const el = input.parentElement.querySelector( ".dcib-phone-validation" );
		if ( isValid ) {
			el.textContent = dcibAdmin.validNumber;
			el.className = "dcib-phone-validation dcib-phone-valid";
			input.style.borderColor = "#46b450";
		} else {
			el.textContent = dcibAdmin.invalidNumber;
			el.className = "dcib-phone-validation dcib-phone-invalid";
			input.style.borderColor = "#dc3232";
		}
	}

	function formatPhoneField( input ) {
		if ( typeof libphonenumber === "undefined" || ! input.value.trim() ) {
			return true;
		}

		try {
			const phoneNumber = libphonenumber.parsePhoneNumber( input.value.trim(), dcibAdmin.defaultCountry );
			if ( ! phoneNumber || ! phoneNumber.isValid() ) {
				validatePhoneField( input );
				return false;
			}

			input.value = phoneNumber.formatNational();
			let telField = input.parentElement.querySelector( ".dcib-phone-tel" );
			if ( ! telField ) {
				telField = document.createElement( "input" );
				telField.type = "hidden";
				telField.name = input.id === "dcib_phone_number" ? "dcib_phone_number_tel" : "dcib_custom_fields[tel][]";
				telField.className = "dcib-phone-tel";
				input.parentElement.appendChild( telField );
			}
			telField.value = phoneNumber.number;
			return true;
		} catch ( error ) {
			validatePhoneField( input );
			return false;
		}
	}

	function refreshPhoneFieldsForCountry() {
		const phoneFields = form.querySelectorAll( "#dcib_phone_number, .custom-value[data-type='phone']" );

		phoneFields.forEach( ( input ) => {
			const telField = input.parentElement.querySelector( ".dcib-phone-tel" );
			if ( telField ) {
				telField.remove();
			}

			validatePhoneField( input );
		} );
	}

	function updateValueField( row ) {
		const type      = row.querySelector( ".custom-type" ).value;
		const container = row.querySelector( ".custom-value" );

		if ( ! container ) {
			return;
		}

		const isTextarea  = container.tagName === "TEXTAREA";
		const currentType = isTextarea ? "address" : container.type;

		container.parentElement.querySelectorAll( ".dcib-phone-tel" ).forEach( ( el ) => el.remove() );

		if ( type === currentType ) {
			return;
		}

		const value      = container.value;
		const isFocused  = document.activeElement === container;
		const selection  = isFocused ? container.selectionStart : -1;

		let el;
		if ( type === "address" ) {
			el = document.createElement( "textarea" );
			el.className = "regular-text custom-value";
			el.rows      = 3;
		} else {
			el = document.createElement( "input" );
			el.type      = type === "custom" ? "text" : type;
			el.className = "regular-text custom-value";
		}

		el.name              = "dcib_custom_fields[value][]";
		el.value             = value;
		el.required          = true;
		el.dataset.type      = type;
		el.setAttribute( "aria-label", dcibAdmin.fieldValueLabel );

		const placeholders = {
			phone:   "+1 (555) 123-4567",
			email:   "name@example.com",
			address: "123 Main St\nSpringfield, IL 62701",
			custom:  "",
		};
		el.placeholder = placeholders[ type ] || "";

		container.replaceWith( el );

		if ( isFocused ) {
			el.focus();
			if ( selection >= 0 ) {
				el.setSelectionRange( selection, selection );
			}
		}
	}

	function getDropdownItems( dropdown ) {
		return Array.from( dropdown.querySelectorAll( ".dcib-dropdown-item:not(.dcib-dropdown-separator)" ) );
	}

	function getFocusedIndex( dropdown ) {
		const items = getDropdownItems( dropdown );
		return items.findIndex( ( item ) => item.classList.contains( "is-focused" ) );
	}

	function focusItem( dropdown, index ) {
		const items = getDropdownItems( dropdown );
		if ( items.length === 0 ) return;
		if ( index < 0 ) index = items.length - 1;
		if ( index >= items.length ) index = 0;
		items.forEach( ( item ) => item.classList.remove( "is-focused" ) );
		items[ index ].classList.add( "is-focused" );
		items[ index ].focus();
	}

	function openDropdown( dropdown ) {
		closeAllDropdowns( dropdown );
		dropdown.classList.add( "is-open" );
		const toggle = dropdown.querySelector( ".dcib-dropdown-toggle" );
		if ( toggle ) {
			toggle.setAttribute( "aria-expanded", "true" );
		}
		focusItem( dropdown, 0 );
	}

	function closeDropdown( dropdown ) {
		dropdown.classList.remove( "is-open" );
		const toggle = dropdown.querySelector( ".dcib-dropdown-toggle" );
		if ( toggle ) {
			toggle.setAttribute( "aria-expanded", "false" );
			toggle.focus();
		}
	}

	function closeAllDropdowns( except ) {
		document.querySelectorAll( ".dcib-dropdown.is-open" ).forEach( ( d ) => {
			if ( except && d === except ) return;
			d.classList.remove( "is-open" );
			const toggle = d.querySelector( ".dcib-dropdown-toggle" );
			if ( toggle ) {
				toggle.setAttribute( "aria-expanded", "false" );
			}
		} );
	}

	function activateDropdownItem( item ) {
		const dropdown = item.closest( ".dcib-dropdown" );
		const action = item.dataset.action;

		if ( action === "remove" ) {
			const row = item.closest( ".dcib-row, .dcib-custom-field" );
			if ( row && window.confirm( dcibAdmin.confirmRemove ) ) {
				row.remove();
				form.dispatchEvent( new Event( "input" ) );
			}
			closeDropdown( dropdown );
			return;
		}

		if ( item.dataset.copy ) {
			copyToClipboard( item.dataset.copy );
		} else if ( action === "copy-key" ) {
			const row = item.closest( ".dcib-row, .dcib-custom-field" );
			const keyField = row ? row.querySelector( ".custom-key" ) : null;
			const keyValue = keyField ? keyField.value : "(CUSTOMKEY)";
			copyToClipboard( keyValue );
		} else if ( action === "copy-link" ) {
			const row = item.closest( ".dcib-row, .dcib-custom-field" );
			const keyField = row ? row.querySelector( ".custom-key" ) : null;
			const key = keyField ? keyField.value : "(KEY)";
			const linkKey = key.replace( /\)$/, "_LINK)" );
			copyToClipboard( linkKey );
		}

		closeDropdown( dropdown );
	}

	function syncCustomFieldDropdown( row ) {
		const type = row.querySelector( ".custom-type" ).value;
		const keyField = row.querySelector( ".custom-key" );
		const linkItem = row.querySelector( ".dcib-dropdown-item[data-action='copy-link']" );
		if ( linkItem ) {
			linkItem.style.display = ( type === "phone" || type === "email" || type === "address" ) ? "" : "none";
		}
		if ( keyField && linkItem ) {
			const linkKey = keyField.value.replace( /\)$/, "_LINK)" );
			linkItem.textContent = linkKey;
		}
		const keyItem = row.querySelector( ".dcib-dropdown-item[data-action='copy-key']" );
		if ( keyField && keyItem ) {
			keyItem.textContent = keyField.value;
		}
	}

	function addCustomField() {
		const container = document.getElementById( "custom-fields" );
		if ( ! container ) {
			return;
		}

		const div = document.createElement( "div" );
		div.className = "dcib-row";
		div.innerHTML =
			'<input type="text" class="regular-text custom-key" name="dcib_custom_fields[key][]" placeholder="(CustomKey)" aria-label="' + dcibAdmin.fieldKeyLabel + '" data-enforce-parens="true" required />' +
			'<select class="custom-type" name="dcib_custom_fields[type][]" aria-label="' + dcibAdmin.fieldTypeLabel + '">' +
				'<option value="phone">' + dcibAdmin.typePhone + "</option>" +
				'<option value="email">' + dcibAdmin.typeEmail + "</option>" +
				'<option value="address">' + dcibAdmin.typeAddress + "</option>" +
				'<option value="custom" selected>' + dcibAdmin.typeCustom + "</option>" +
			"</select>" +
			'<input type="text" class="regular-text custom-value" name="dcib_custom_fields[value][]" placeholder="" aria-label="' + dcibAdmin.fieldValueLabel + '" required />' +
			'<div class="dcib-dropdown dcib-row-actions">' +
				'<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false">' + dcibAdmin.actions + "</button>" +
				'<div class="dcib-dropdown-menu" role="menu">' +
					'<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-action="copy-key">(CUSTOMKEY)</div>' +
					'<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-action="copy-link" style="display:none">(CUSTOMKEY_LINK)</div>' +
					'<div class="dcib-dropdown-separator"></div>' +
					'<div class="dcib-dropdown-item danger" role="menuitem" tabindex="-1" data-action="remove">' + dcibAdmin.remove + "</div>" +
				"</div>" +
			"</div>";

		container.appendChild( div );
		updateValueField( div );
		syncCustomFieldDropdown( div );
	}

	function handleDropdownKeydown( e ) {
		const dropdown = e.currentTarget;
		if ( ! dropdown.classList.contains( "is-open" ) ) return;

		const items = getDropdownItems( dropdown );
		const currentIndex = getFocusedIndex( dropdown );

		switch ( e.key ) {
			case "ArrowDown":
				e.preventDefault();
				focusItem( dropdown, currentIndex + 1 );
				break;
			case "ArrowUp":
				e.preventDefault();
				focusItem( dropdown, currentIndex - 1 );
				break;
			case "Home":
				e.preventDefault();
				focusItem( dropdown, 0 );
				break;
			case "End":
				e.preventDefault();
				focusItem( dropdown, items.length - 1 );
				break;
			case "Enter":
			case " ":
				e.preventDefault();
				if ( currentIndex >= 0 && items[ currentIndex ] ) {
					activateDropdownItem( items[ currentIndex ] );
				}
				break;
			case "Escape":
				e.preventDefault();
				closeDropdown( dropdown );
				break;
			case "Tab":
				closeDropdown( dropdown );
				break;
		}
	}

	// --- Event delegation ---

	document.addEventListener( "click", ( e ) => {
		const target = e.target;

		if ( target.classList.contains( "dcib-dropdown-toggle" ) ) {
			e.preventDefault();
			const dropdown = target.closest( ".dcib-dropdown" );
			if ( dropdown.classList.contains( "is-open" ) ) {
				closeDropdown( dropdown );
			} else {
				openDropdown( dropdown );
			}
			return;
		}

		const item = target.closest( ".dcib-dropdown-item" );
		if ( item && target.closest( ".dcib-dropdown.is-open" ) ) {
			activateDropdownItem( item );
			return;
		}

		if ( target.dataset.action === "add-field" ) {
			addCustomField();
			return;
		}

		if ( ! target.closest( ".dcib-dropdown" ) ) {
			closeAllDropdowns();
		}
	} );

	form.addEventListener( "change", ( e ) => {
		const target = e.target;

		if ( target.id === "dcib_default_country" ) {
			dcibAdmin.defaultCountry = target.value;
			refreshPhoneFieldsForCountry();
		}

		if ( target.classList.contains( "custom-type" ) ) {
			const row = target.closest( ".dcib-row, .dcib-custom-field" );
			updateValueField( row );
			syncCustomFieldDropdown( row );
		}

		formChanged = true;
	} );

	form.addEventListener( "input", ( e ) => {
		const target = e.target;

		if ( target.dataset.enforceParens !== undefined ) {
			enforceParentheses( target );
			const row = target.closest( ".dcib-row, .dcib-custom-field" );
			syncCustomFieldDropdown( row );
		}

		if ( target.id === "dcib_phone_number" || ( target.classList.contains( "custom-value" ) && target.dataset.type === "phone" ) ) {
			validatePhoneField( target );
		}

		formChanged = true;
	} );

	form.addEventListener( "submit", ( event ) => {
		const phoneFields = form.querySelectorAll( "#dcib_phone_number, .custom-value[data-type='phone']" );
		let valid = true;
		phoneFields.forEach( ( input ) => {
			if ( ! formatPhoneField( input ) ) {
				valid = false;
			}
		} );
		if ( ! valid ) {
			event.preventDefault();
			return;
		}
		formChanged = false;
	} );

	window.addEventListener( "beforeunload", ( event ) => {
		if ( formChanged ) {
			event.preventDefault();
			event.returnValue = "";
		}
	} );

	// Init dropdowns.
	document.addEventListener( "keydown", ( e ) => {
		const openDropdown = document.querySelector( ".dcib-dropdown.is-open" );
		if ( openDropdown ) {
			handleDropdownKeydown.call( openDropdown, e );
		}
	} );

	document.querySelectorAll( ".dcib-row, .dcib-custom-field" ).forEach( ( row ) => {
		syncCustomFieldDropdown( row );
	} );
} )();
