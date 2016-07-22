(function(){

	var exampleDialog = function(editor){
		return {
			title: editor.lang.vbulletin.image_settings,
			onShow: function(){
				var dialog = CKEDITOR.dialog.getCurrent();
				var editor = dialog.getParentEditor();

				var postData = {
					ajax: 1,
					attachmentid: editor.current_attachmentid,
					'do': 'loadimageconfig',
					allowsmilie: 1,
					securitytoken:  editor.config.vbulletin.securitytoken,
					posthash: editor.config.vbulletin.attachinfo.posthash,
					poststarttime: editor.config.vbulletin.attachinfo.poststarttime,
					contentid: editor.config.vbulletin.contentid
				};

				var responseXML = CKEDITOR.vbajax.open({
					url: fetch_ajax_url('ajax.php'),
					type: 'POST',
					data: postData,
					async: false,
					dataType: 'xml'
				}).responseXML;

				if (responseXML == null || responseXML.firstChild == null)
				{
					dialog.hide();
					return;
				}

				var extension = fetch_tags(responseXML, 'extension')[0].firstChild.nodeValue;

				var sizeelement = dialog.getContentElement('attachmentConfig', 'size');
				var linkelement = dialog.getContentElement('attachmentConfig', 'link');
				if (extension == 'pdf')
				{
					linkelement.disable();
					linkelement.getElement().addClass("hidden");
					sizeelement.disable();
					sizeelement.getElement().addClass("hidden");
				}
				else
				{
					linkelement.enable();
					linkelement.getElement().removeClass("hidden");
					sizeelement.enable();
					sizeelement.getElement().removeClass("hidden");
				}
				var alignment = fetch_tags(responseXML, 'alignment')[0];
				var element = dialog.getContentElement('attachmentConfig', 'alignment');
				if (alignment && alignment.firstChild)
				{
					element.setValue(alignment.firstChild.nodeValue);
				}
				else
				{
					element.setValue(0);
				}
				var size = fetch_tags(responseXML, 'size')[0];
				if (size && size.firstChild)
				{
					sizeelement.setValue(size.firstChild.nodeValue);
				}
				else
				{
					sizeelement.setValue('thumbnail');
				}
				var title = fetch_tags(responseXML, 'title')[0];
				if (title && title.firstChild)
				{
					var element = dialog.getContentElement('attachmentConfig', 'title');
					element.setValue(title.firstChild.nodeValue);
				}
				var description = fetch_tags(responseXML, 'description')[0];
				if (description && description.firstChild)
				{
					var element = dialog.getContentElement('attachmentConfig', 'description');
					element.setValue(description.firstChild.nodeValue);
				}

				var canstyle = fetch_tags(responseXML, 'canstyle')[0].firstChild.nodeValue;
				var styleelement = dialog.getContentElement('attachmentConfig', 'styles');
				var styles = fetch_tags(responseXML, 'styles')[0];
				if (canstyle == 1)
				{
					if (styles && styles.firstChild)
					{
						styleelement.setValue(styles.firstChild.nodeValue);
					}
				}
				else
				{
					styleelement.disable();
					styleelement.getElement().addClass("hidden");
				}

				// 0 = default (lightbox) // 1 = url // 2 = none
				var link = fetch_tags(responseXML, 'link')[0];
				var linktargetelement = dialog.getContentElement('attachmentConfig', 'linktarget');
				var linkvalue = 0;
				var linktargetvalue = 0;
				if (link && link.firstChild)
				{
					linkvalue = link.firstChild.nodeValue;
					if (linkvalue == 1)
					{
						var linkurl = fetch_tags(responseXML, 'linkurl')[0];
						if (linkurl && linkurl.firstChild)
						{
							var element2 = dialog.getContentElement('attachmentConfig', 'linkurl');
							element2.setValue(PHP.unhtmlspecialchars(linkurl.firstChild.nodeValue));

							var linktarget = fetch_tags(responseXML, 'linktarget')[0];
							if (linktarget && linktarget.firstChild)
							{
								linktargetvalue = linktarget.firstChild.nodeValue;
							}
						}
						else
						{
							linkvalue = 0;
						}
					}
				}
				linkelement.setValue(linkvalue);
				linktargetelement.setValue(linktargetvalue);
			},
			onOk: function()
			{
				var dialog = CKEDITOR.dialog.getCurrent();
				var editor = dialog.getParentEditor();

				var data = {
					ajax: 1,
					attachmentid: editor.current_attachmentid,
					'do': 'saveimageconfig',
					securitytoken:  editor.config.vbulletin.securitytoken,
					posthash: editor.config.vbulletin.attachinfo.posthash,
					poststarttime: editor.config.vbulletin.attachinfo.poststarttime,
					contentid: editor.config.vbulletin.contentid
				};
				var alignment = dialog.getContentElement('attachmentConfig', 'alignment');
				if (alignment.getValue())
				{
					data.alignment = alignment.getValue();
				}
				var size = dialog.getContentElement('attachmentConfig', 'size');
				if (size.getValue())
				{
					data.size = size.getValue();
				}
				var title = dialog.getContentElement('attachmentConfig', 'title');
				if (title.getValue())
				{
					data.title = title.getValue();
				}
				var description = dialog.getContentElement('attachmentConfig', 'description');
				if (description.getValue())
				{
					data.description = description.getValue();
				}
				var styles = dialog.getContentElement('attachmentConfig', 'styles');
				if (styles.getValue())
				{
					data.styles = styles.getValue();
				}
				var link = dialog.getContentElement('attachmentConfig', 'link');
				if (link.getValue())
				{
					data.link = link.getValue();
				}
				var linkurl = dialog.getContentElement('attachmentConfig', 'linkurl');
				if (link.getValue() == 1 && linkurl.getValue())
				{
					data.linkurl = linkurl.getValue();
				}
				var linktarget = dialog.getContentElement('attachmentConfig', 'linktarget');
				if (link.getValue() == 1 && linktarget.getValue())
				{
					data.linktarget = linktarget.getValue();
				}
				var attachment = editor.document.getById('vbattach_' + editor.current_attachmentid);
				if (attachment)
				{
					attachment.removeClass('size_thumbnail');
					attachment.removeClass('size_medium');
					attachment.removeClass('size_large');
					attachment.removeClass('size_fullsize');
					attachment.removeClass('align_left');
					attachment.removeClass('align_center');
					attachment.removeClass('align_right');

					for (var x in attachment.$.style)
					{
						try
						{
							if (typeof(attachment.$.style[x]) == 'string')
							{
								attachment.setStyle(x, '');
							}
						}
						catch(e)
						{/**nothing to do, just keep going**/}
					}

					if (alignment.getValue())
					{
						attachment.addClass('align_' + PHP.htmlspecialchars(alignment.getValue()));
					}
					if (size.getValue())
					{
						attachment.addClass('size_' + PHP.htmlspecialchars(size.getValue()));
					}
					if (styles.getValue())
					{
						var block = styles.getValue().split(';');
						for (var i = 0; i < block.length; i++)
						{
							if (block[i])
							{
								var bits = block[i].split(':');
								if (bits.length == 2)
								{
									// this isn't working properly in some cases ...
									attachment.setStyle(PHP.htmlspecialchars(bits[0]), PHP.htmlspecialchars(bits[1]));
								}
							}
						}
					}
				}

				CKEDITOR.vbajax.open({
					url: fetch_ajax_url('ajax.php'),
					type: 'POST',
					data: data,
					dataType: 'xml'
				})
			},
			minWidth: '600',
			minHeight: '290',
			contents: [{
				id: 'attachmentConfig',
        elements:[{
        	type: 'radio',
          id: 'alignment',
          label: editor.lang.vbulletin.alignment,
          labelLayout: 'horizontal',
          items: [[editor.lang.vbulletin.none,'0'],[editor.lang.common.alignLeft,'left'],[editor.lang.common.alignCenter,'center'],[editor.lang.common.alignRight,'right']]
        },{
        	type: 'radio',
          id: 'size',
          label: editor.lang.vbulletin.size,
          labelLayout: 'horizontal',
          items: [[editor.lang.vbulletin.thumbnail,'thumbnail'],[editor.lang.vbulletin.medium,'medium'],[editor.lang.vbulletin.large,'large'],[editor.lang.vbulletin.fullsize,'fullsize']]
        },{
        	type: 'text',
          id: 'title',
          label: editor.lang.vbulletin.title,
          labelLayout: 'horizontal'
        },{
        	type: 'textarea',
          id: 'description',
          label: editor.lang.vbulletin.description,
          labelLayout: 'horizontal'
        },{
        	type: 'text',
          id: 'styles',
          label: editor.lang.common.styles,
          labelLayout: 'horizontal'
        },
		{
        	type: 'radio',
          id: 'link',
          label: editor.lang.vbulletin.linkType,
          labelLayout: 'horizontal',
          items: [[editor.lang.vbulletin.linkdefault, '0'],[editor.lang.common.url, '1'], [editor.lang.vbulletin.none, '2']],
		  onChange: function() {
			var dialog = CKEDITOR.dialog.getCurrent();
			var linkelement = dialog.getContentElement('attachmentConfig', 'link');
			var linkurlelement = dialog.getContentElement('attachmentConfig', 'linkurl');
			var linktargetelement = dialog.getContentElement('attachmentConfig', 'linktarget');
			if (linkelement.isEnabled() && this.getValue() == 1)
			{
				linkurlelement.enable();
				linktargetelement.enable();
			}
			else
			{
				linkurlelement.disable();
				linktargetelement.disable();
			}
		  }
        },{
        	type: 'text',
          id: 'linkurl',
          label: editor.lang.vbulletin.linkURL,
          labelLayout: 'horizontal'
        }, {
			type: 'select',
			id: 'linktarget',
			label: editor.lang.common.target,
			labelLayout: 'horizontal',
			items: [[editor.lang.common.targetSelf, '0'], [editor.lang.common.targetNew, '1']]
		}]
			}]
		};
	};

	CKEDITOR.dialog.add('attachment', function(editor) {
		return exampleDialog(editor);
	});

})();