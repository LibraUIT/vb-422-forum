/*
Copyright (c) 2003-2010, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

(function()
{
	var defaultToPixel = CKEDITOR.tools.cssLength;
	
	// vBulletin Start
	var borderPattern = /wysiwyg_cms_table_(\S+)/;
	// vBulltin End

	var commitValue = function( data )
	{
		var id = this.id;
		if ( !data.info )
			data.info = {};
		data.info[id] = this.getValue();
	};

	function tableColumns( table )
	{
		var cols = 0, maxCols = 0;
		for ( var i = 0, row, rows = table.$.rows.length; i < rows; i++ )
		{
			row = table.$.rows[ i ], cols = 0;
			for ( var j = 0, cell, cells = row.cells.length; j < cells; j++ )
			{
				cell = row.cells[ j ];
				cols += cell.colSpan;
			}

			cols > maxCols && ( maxCols = cols );
		}

		return maxCols;
	}

	function tableDialog( editor, command )
	{
		var makeElement = function( name )
			{
				return new CKEDITOR.dom.element( name, editor.document );
			};

		var dialogadvtab = editor.plugins.dialogadvtab;

		// vBulletin Start
		var addTableClass = function(element, table_type)
			{
				element.removeAttribute('class');
				
				var suffix = (element.getName() == 'table') ? '' : '_' + element.getName();
				if (table_type != '')
				{
					table_type = (table_type == '') ? '' : '_' + table_type;
					element.addClass('wysiwyg_cms_table' + table_type + suffix);
				}
				element.addClass('wysiwyg_dashes' + suffix);
			};
		// vBulletin End

		return {
			title : editor.lang.table.title,
			minWidth : 310,
			minHeight : CKEDITOR.env.ie ? 310 : 280,

			onLoad : function()
			{
				var dialog = this;

				var styles = dialog.getContentElement( 'advanced', 'advStyles' );

				if ( styles )
				{
					styles.on( 'change', function( evt )
						{
							// Synchronize width value.
							var width = this.getStyle( 'width', '' ),
								txtWidth = dialog.getContentElement( 'info', 'txtWidth' );

							txtWidth && txtWidth.setValue( width, true );

							// Synchronize height value.
							var height = this.getStyle( 'height', '' ),
								txtHeight = dialog.getContentElement( 'info', 'txtHeight' );

							txtHeight && txtHeight.setValue( height, true );
						});
				}
			},

			onShow : function()
			{
				// Detect if there's a selected table.
				if (editor.mode == 'wysiwyg')
				{
					var selection = editor.getSelection(),
						ranges = selection.getRanges(),
						selectedTable = null;
				}
				else
				{
					var selection = null, ranges = null, selectedTable = null;
				}
					
				var rowsInput = this.getContentElement( 'info', 'txtRows' ),
					colsInput = this.getContentElement( 'info', 'txtCols' ),
					widthInput = this.getContentElement( 'info', 'txtWidth' ),
					heightInput = this.getContentElement( 'info', 'txtHeight' );

				if ( command == 'tableProperties' )
				{
					if ( ( selectedTable = selection.getSelectedElement() ) )
						selectedTable = selectedTable.getAscendant( 'table', true );
					else if ( ranges.length > 0 )
					{
						// Webkit could report the following range on cell selection (#4948):
						// <table><tr><td>[&nbsp;</td></tr></table>]
						if ( CKEDITOR.env.webkit )
							ranges[ 0 ].shrink( CKEDITOR.NODE_ELEMENT );

						var rangeRoot = ranges[0].getCommonAncestor( true );
						selectedTable = rangeRoot.getAscendant( 'table', true );
					}

					// Save a reference to the selected table, and push a new set of default values.
					this._.selectedElement = selectedTable;
				}

				// Enable or disable the row, cols, width fields.
				if ( selectedTable )
				{
					this.setupContent( selectedTable );
					rowsInput && rowsInput.disable();
					colsInput && colsInput.disable();
				}
				else
				{
					rowsInput && rowsInput.enable();
					colsInput && colsInput.enable();
				}

				// Call the onChange method for the widht and height fields so
				// they get reflected into the Advanced tab.
				widthInput && widthInput.onChange();
				heightInput && heightInput.onChange();
			},
			onOk : function()
			{			
				if ( this._.selectedElement )
				{
					var selection = editor.getSelection(),
						bms = this._.selectedElement && selection.createBookmarks();
				}

				var table = this._.selectedElement || makeElement( 'table' ),
					me = this,
					data = {};

				this.commitContent( data, table );

				if (editor.mode == 'enhancedsource')
				{
					var output_html = '';
					if (CKEDITOR.env.ie)
					{
						if (editor.savedselection)
						{
							var selected_text = editor.savedselection.text;
						}
					}
					else
					{
						var selected_text = editor.esGetSelectionText();
					}
					
					if (data.info)
					{
						var config = {
							'rows'    : parseInt(data.info.txtRows, 10),
							'columns' : parseInt(data.info.txtCols, 10),
							'width'   : data.info.txtWidth,
							'class'   : data.info.istyle
						};	
					}
					else
					{
						return;
					}
					
					var table_options = [];
					if (config.width)
					{
						config.width = config.width.replace(/px$/i, '');
						table_options.push('width: ' + config.width);
					}
					if (config['class'])
					{
						table_options.push('class: ' + config['class']);
					}
					if (table.getAttribute('align'))
					{
						table_options.push('align: ' + table.getAttribute('align'));
					}

					output_html += '[table';
					if (table_options.length)
					{
						output_html += '="' + table_options.join(', ') + '"';
					}
					output_html += ']\n';

					for (row = 0; row < config.rows; row++)
					{
						output_html += '[tr]\n';
						for (col = 0; col < config.columns; col++)
						{
							output_html += '\t[td]' + selected_text + '[/td]\n';
							selected_text = '';
						}
						output_html += '[/tr]\n';
					}
					output_html += '[/table]\n';
					
					editor.insertText(output_html);
					
					return;
				}

				if ( data.info )
				{
					var info = data.info;

					// vBulletin Start
					addTableClass(table, info.istyle);
					// vBulletin End

					// Generate the rows and cols.
					if ( !this._.selectedElement )
					{
						var tbody = table.append( makeElement( 'tbody' ) ),
							rows = parseInt( info.txtRows, 10 ) || 0,
							cols = parseInt( info.txtCols, 10 ) || 0;

						for ( var i = 0 ; i < rows ; i++ )
						{
							var row = tbody.append( makeElement( 'tr' ) );
							addTableClass(row, info.istyle);
							for ( var j = 0 ; j < cols ; j++ )
							{
								var cell = row.append( makeElement( 'td' ) );
								// vBulletin Start
								addTableClass(cell, info.istyle);
								// vBulletin End
								if ( !CKEDITOR.env.ie )
									cell.append( makeElement( 'br' ) );
							}
						}
					}
					// vBulletin Start
					else
					{
						var tr_tags = table.getElementsByTag('tr');
						for ( var i = 0 ; i < tr_tags.count(); i++ )
						{
							addTableClass(tr_tags.getItem(i), info.istyle);
						}
						
						var td_tags = table.getElementsByTag('td');
						for ( var i = 0 ; i < td_tags.count(); i++ )
						{
							addTableClass(td_tags.getItem(i), info.istyle);
						}
					}
					// vBulletin End

					// Modify the table headers. Depends on having rows and cols generated
					// correctly so it can't be done in commit functions.

					// Should we make a <thead>?
					var headers = info.selHeaders;
					if ( !table.$.tHead && ( headers == 'row' || headers == 'both' ) )
					{
						var thead = new CKEDITOR.dom.element( table.$.createTHead() );
						tbody = table.getElementsByTag( 'tbody' ).getItem( 0 );
						var theRow = tbody.getElementsByTag( 'tr' ).getItem( 0 );

						// Change TD to TH:
						for ( i = 0 ; i < theRow.getChildCount() ; i++ )
						{
							var th = theRow.getChild( i );
							// Skip bookmark nodes. (#6155)
							if ( th.type == CKEDITOR.NODE_ELEMENT && !th.data( 'cke-bookmark' ) )
							{
								th.renameNode( 'th' );
								th.setAttribute( 'scope', 'col' );
							}
						}
						thead.append( theRow.remove() );
					}

					if ( table.$.tHead !== null && !( headers == 'row' || headers == 'both' ) )
					{
						// Move the row out of the THead and put it in the TBody:
						thead = new CKEDITOR.dom.element( table.$.tHead );
						tbody = table.getElementsByTag( 'tbody' ).getItem( 0 );

						var previousFirstRow = tbody.getFirst();
						while ( thead.getChildCount() > 0 )
						{
							theRow = thead.getFirst();
							for ( i = 0; i < theRow.getChildCount() ; i++ )
							{
								var newCell = theRow.getChild( i );
								if ( newCell.type == CKEDITOR.NODE_ELEMENT )
								{
									newCell.renameNode( 'td' );
									newCell.removeAttribute( 'scope' );
								}
							}
							theRow.insertBefore( previousFirstRow );
						}
						thead.remove();
					}

					// Should we make all first cells in a row TH?
					if ( !this.hasColumnHeaders && ( headers == 'col' || headers == 'both' ) )
					{
						for ( row = 0 ; row < table.$.rows.length ; row++ )
						{
							newCell = new CKEDITOR.dom.element( table.$.rows[ row ].cells[ 0 ] );
							newCell.renameNode( 'th' );
							newCell.setAttribute( 'scope', 'row' );
						}
					}

					// Should we make all first TH-cells in a row make TD? If 'yes' we do it the other way round :-)
					if ( ( this.hasColumnHeaders ) && !( headers == 'col' || headers == 'both' ) )
					{
						for ( i = 0 ; i < table.$.rows.length ; i++ )
						{
							row = new CKEDITOR.dom.element( table.$.rows[i] );
							if ( row.getParent().getName() == 'tbody' )
							{
								newCell = new CKEDITOR.dom.element( row.$.cells[0] );
								newCell.renameNode( 'td' );
								newCell.removeAttribute( 'scope' );
							}
						}
					}

					// Set the width and height.
					info.txtHeight ? table.setStyle( 'height', info.txtHeight ) : table.removeStyle( 'height' );
					info.txtWidth ? table.setStyle( 'width', info.txtWidth ) : table.removeStyle( 'width' );

					if ( !table.getAttribute( 'style' ) )
						table.removeAttribute( 'style' );
				}

				// Insert the table element if we're creating one.
				if ( !this._.selectedElement )
				{
					editor.insertElement( table );
					// Override the default cursor position after insertElement to place
					// cursor inside the first cell (#7959), IE needs a while.
					setTimeout( function()
						{
							var firstCell = new CKEDITOR.dom.element( table.$.rows[ 0 ].cells[ 0 ] );
							var range = new CKEDITOR.dom.range( editor.document );
							range.moveToPosition( firstCell, CKEDITOR.POSITION_AFTER_START );
							range.select( 1 );
						}, 0 );
				}
				// Properly restore the selection, (#4822) but don't break
				// because of this, e.g. updated table caption.
				else
					try { selection.selectBookmarks( bms ); } catch( er ){}

				return true;
			},
			contents : [
				{
					id : 'info',
					label : editor.lang.table.title,
					elements :
					[
						{
							type : 'text',
							id : 'txtRows',
							'default' : 3,
							label : editor.lang.table.rows,
							required : true,
							controlStyle : 'width:5em',
							validate : function()
							{
								var pass = true,
									value = this.getValue();
								pass = pass && CKEDITOR.dialog.validate.integer()( value )
									&& value > 0;
								if ( !pass )
								{
									alert( editor.lang.table.invalidRows );
									this.select();
								}
								return pass;
							},
							setup : function( selectedElement )
							{
								this.setValue( selectedElement.$.rows.length );
							},
							commit : commitValue
						},
						{
							type : 'text',
							id : 'txtCols',
							'default' : 2,
							label : editor.lang.table.columns,
							required : true,
							controlStyle : 'width:5em',
							validate : function()
							{
								var pass = true,
									value = this.getValue();
								pass = pass && CKEDITOR.dialog.validate.integer()( value )
									&& value > 0;
								if ( !pass )
								{
									alert( editor.lang.table.invalidCols );
									this.select();
								}
								return pass;
							},
							setup : function( selectedTable )
							{
								this.setValue( selectedTable.$.rows[0].cells.length);
							},
							commit : commitValue
						},
						{
							type : 'hbox',
							widths : [ '5em' ],
							children :
							[
								{
									type : 'text',
									id : 'txtWidth',
									controlStyle : 'width:5em',
									label : editor.lang.common.width,
									title : editor.lang.common.cssLengthTooltip,
									'default' : '500',
									getValue : defaultToPixel,
									validate : CKEDITOR.dialog.validate.cssLength( editor.lang.common.invalidCssLength.replace( '%1', editor.lang.common.width ) ),
						
									onChange : function()
									{
										var styles = this.getDialog().getContentElement( 'advanced', 'advStyles' );
										styles && styles.updateStyle( 'width', this.getValue() );
									},
						
									setup : function( selectedTable )
									{
										var val = selectedTable.getStyle( 'width' );
										val && this.setValue( val );
									},
									commit : commitValue
								}
							]
						},
						{
							id : 'istyle',
							type : 'select',
							label : editor.lang.vbulletin.table_style,
							'default' : '',
							items :
							[
								[ editor.lang.vbulletin.no_borders , ''],
								[ editor.lang.vbulletin.outer_border , 'outer_border'],
								[ editor.lang.vbulletin.full_grid , 'grid']
							],
							setup : function( selectedTable )
							{
								var borderMatch = borderPattern.exec( selectedTable.$.className );
								if (borderMatch)
									this.setValue(borderMatch[1]);
							},
							commit : commitValue
						},
						{
							id : 'cmbAlign',
							type : 'select',
							label : editor.lang.common.align,
							'default' : '',
							items :
							[
								[ editor.lang.common.notSet , ''],
								[ editor.lang.common.alignLeft , 'left'],
								[ editor.lang.common.alignCenter , 'center'],
								[ editor.lang.common.alignRight, 'right']
							],
							setup : function( selectedTable )
							{
								this.setValue(selectedTable.getAttribute( 'align' ) || '');
							},
							commit : function(data, selectedTable)
							{
								if (this.getValue())
								{
									selectedTable.setAttribute('align', this.getValue());
								}
								else
								{
									selectedTable.removeAttribute('align');
								}
							}
						}
					]
				}
			]
		};
	}

	CKEDITOR.dialog.add( 'table', function( editor )
		{
			return tableDialog( editor, 'table' );
		} );
	CKEDITOR.dialog.add( 'tableProperties', function( editor )
		{
			return tableDialog( editor, 'tableProperties' );
		} );
})();
