
class CcssFront {

    rules = [];
    rule = -1;
    area = -1;
    style = null;
    images = [];

    constructor(rules) {
        this.rules = rules;
        this.rules.forEach(rule => {
            rule.rule_data.areas.forEach(area => {
                const el = $single(area.selector);
                el.classList.add('editing-element');
                el.setAttribute('data-selector', area.selector);
                el.setAttribute('data-form_id', area.id);
                el.setAttribute('data-rule_id', rule.id);
            });
            if  (rule.rule_data.images) {
                rule.rule_data.images.forEach((img, ind) => {
                    const el = $single(this.imgSelector(img.image));
                    if (el) {
                        el.classList.add('editing-image');
                        el.title = ccssStr.replaceableImage;
                        el.setAttribute('data-rule_id', rule.id);
                        el.setAttribute('data-index', ind);
                        if (img.replacement) {
                            el.removeAttribute('srcset');
                            el.removeAttribute('sizes');
                            el.src = img.replacement;
                        }
                        this.images.push(img);
                    }
                });
            }
        });
        this.start();
    }
    
    imgSelector(src) {
        const ext = src.split('.').pop();
        const name = src.replace(`.${ext}`, '');
        return `img[src^="${name}"]`;
    }
    
    setMeasure(element) {
        const sel = element.nextElementSibling;
        if (/^(normal|inherit|none|initial)$/.test(element.value)) {
            sel.disabled = true;
            return;
        }
        sel.disabled = false;
        const num = element.value.trim().replace(/[^0-9\.]+/, '');
        const msr = element.value.trim().replace(/[0-9\.]+/, '');
        element.value = num;
        sel.value = msr ? msr : 'px';
    }

    start() {
        this.addButtons();
        rootEvent('.editing-page .editing-element', 'click', event => {
            event.preventDefault();
            this.edit(event.target.closest('.editing-element'));
        });
        rootEvent(
            '.editing-page .popup-stage .input.measure input[type="text"]', 
            'input', 
            debounce(event => this.setMeasure(event.target), 500)
        );
        $apply('.input.measure input[type="text"]', inp => this.setMeasure(inp));
        $apply('.formline .input.color', elem => {
            const text = $single('input[type="text"]', elem);
            const color = $single('input[type="color"]', elem);
            const clear = $single('.clear-input', elem);
            color.addEventListener('input', () => {
                text.value = color.value;
            });
            clear.addEventListener('click', () => {
                text.value = '';
            });
        });

        rootEvent('.editing-page .popup-stage button.cancel', 'click', event => {
            event.preventDefault();
            this.hideWindow();
        });
        rootEvent('form[data-selector]', 'submit', event => {
            event.preventDefault();
            const form = event.target;
            $apply('.input.measure input[type="text"]', elem => {
                const m = elem.nextElementSibling.value;
                elem.value = elem.value ? elem.value + m : '';
            }, form);
            const selector = form.dataset.selector;
            this.applyRule(selector, form);
            this.showSaveButton();
        });

        let mediaUploader;
        rootEvent('.editing-page img.editing-image', 'click', event => {
            event.preventDefault();
            const img = event.target;
            this.rule = img.dataset.rule_id;
            // console.log('img', img)
            const openMediaUploader = img => {
                if (mediaUploader) {
                    mediaUploader.state().set('title', ccssStr.chooseReplacement);
                    mediaUploader.off('select');
                    mediaUploader.on('select', () => handleImageSelect(img));
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media({
                    title: ccssStr.chooseReplacement,
                    button: { text: ccssStr.selectImage },
                    multiple: false
                });

                mediaUploader.on('open', () => {
                    setTimeout(() => {
                        if (!ccssStr.imgCfg.includes('upload')) {
                            let uploadTab = document.querySelector('#menu-item-upload');
                            if (uploadTab) {
                                uploadTab.remove();
                            }
                        }
                        const removeLinks = () => {
                            const rem = selector => {
                                const links = document.querySelectorAll(selector);
                                links.forEach(el => el.remove());
                            };
                            if (!ccssStr.imgCfg.includes('edit')) {
                                rem('.edit-attachment');
                            }
                            if (!ccssStr.imgCfg.includes('delete')) {
                                rem('.delete-attachment');
                            }
                        };
                        let observer = new MutationObserver(removeLinks);
                        observer.observe(document.body, { childList: true, subtree: true });
                        removeLinks();
                    }, 100);
                });

                mediaUploader.on('select', () => handleImageSelect(img));
                mediaUploader.open();
            };
            const handleImageSelect = img => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                if (img.srcset) {
                    img.removeAttribute('srcset');
                }
                if (img.sizes) {
                    img.removeAttribute('sizes');
                }
                const ind = img.dataset.index || this.images.findIndex(e => e.image == img.src);
                const nimg = this.images[ind];
                nimg.replacement = attachment.url;
                this.images[ind] = nimg;
                img.src = attachment.url;
                this.showSaveButton();
            };
            openMediaUploader(img);
        });
    }
    
    showSaveButton() {
        document.body.classList.add('css-modified');
    }
    
    addButtons() {
        const start = tag(
            'a', 
            { 
                class: 'edit-page', 
                title: ccssStr.editButtonTitle ,
                href: '#',
            }, 
            '<span class="dashicons dashicons-edit"></span>'
        );
        start.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('editing-page');
        });
        document.body.appendChild(start);
        const save = tag(
            'a', 
            { 
                class: 'save-css', 
                title: ccssStr.saveButtonTitle,
                href: '#'
            }, 
            '<span class="dashicons dashicons-saved"></span>'
        );
        save.addEventListener('click', event => {
            event.preventDefault();
            this.saveRule().then(res => {
                this.message(res.message, !!res.error);
                document.body.classList.remove('editing-page', 'css-modified');
            });
        });
        document.body.appendChild(save);
    }

    message(msg, err = false) {
        const close = tag('a', { href: '#', class: 'close-msg' }, '<span class="dashicons dashicons-no"></span>');
        close.addEventListener('click', event => {
            event.preventDefault();
            this.closeMessage();
        });
        const cls = err ? 'error ' : '';
        const message = tag('div', { class: `${cls}user-msg-message` }, msg);
        const display = tag('div', { class: 'user-msg' }, [ close, message ]);
        document.body.appendChild(display);
        setTimeout(() => document.body.classList.add('show-message'), 50);
    }

    closeMessage() {
        document.body.classList.remove('show-message');
        setTimeout(() => $single('.user-msg').remove(), 500);
    }

    // Open editor
    edit(elem) {
        this.rule = elem.dataset.rule_id;
        this.area = elem.dataset.form_id;
        this.createWindow(ccssStr.editorTitle);
        setTimeout(() => this.showWindow(), 80);
    }
    
    // createWindow
    createWindow(title) {
        this.style = this.getCSSObj(`style#style_${this.rule}`);
        const form = $single(`#element_${this.area}`);
        this.setId(form);
        const close = tag('a', { class: 'popup-close', href: '#' }, '<span class="dashicons dashicons-no"></span>');
        close.addEventListener('click', event => { event.preventDefault(); this.hideWindow(); });
        const label = tag('div', { class: 'popup-title' }, title);
        const stage = tag('div', { class: 'popup-stage' }, form);
        const win = tag('div', { class: 'popup-window' }, [close, label, stage]);
        const popup = tag('div', { class: 'popup-overlay' }, win);
        document.body.appendChild(popup);
    }

    // dataId
    setId(elem) {
        $apply('*[data-id]', el => { el.id = el.dataset.id; }, elem);
    }
    setDataId(elem) {
        $apply('*[id]', el => {
            if (el.id.trim() != '') {
                el.dataset.id = el.id;
                el.id = '';
            }
        }, elem);
    }

    // show window
    showWindow() {
        document.body.classList.add('open-popup');
        this.fillForm();
    }

    // hide window
    hideWindow() {
        document.body.classList.remove('open-popup');
        setTimeout(() => {
            const wrap = $single('.edit-form.hidden');
            $apply('.popup-stage form[data-selector]', elem => {
                this.setDataId(elem);
                wrap.appendChild(elem);
            });
            $apply('.popup-overlay', elem => elem.remove());
        }, 500);
    }

    fillForm() {
        const form = $single('.popup-stage form[data-selector]');
        if (form) {
            const selector = form.dataset.selector;
            const vals = this.style[selector];
            Array.from(form.elements).forEach((elem,  index) => {
                if (elem.id.includes('_mtype')) {
                    return true;
                }
                if (vals[elem.id] !== undefined) {
                    if (!!form.elements[index + 1] && form.elements[index + 1].id == elem.id + '_mtype') {
                        form.elements[index + 1].value = vals[elem.id].replace(/[0-9]+/, '');
                        vals[elem.id] = vals[elem.id].replace(/[^0-9]+/, '');
                    }
                    if (elem.id.toLowerCase().includes('color') && vals[elem.id]) {
                        vals[elem.id] = this.rgbToHex(vals[elem.id]);
                    }
                    elem.value =  vals[elem.id] ?? '';
                }
            });
        }
    }

    // Send changes to server
    saveRule() {
        console.log(`RULE: ${this.rule}`);
        return new Promise(resolve => {
            ajax(ccssStr.ajaxurl, { 
                action: 'save_rule',
                style: JSON.stringify(this.style),
                images: JSON.stringify(this.images),
                rule_id: this.rule
            }, 'POST').then(res => res.json()).then(json => resolve(json));
        });
    }

    // Apply rule changes to the page
    applyRule(selector, form) {
        let props = formValues(form);
        this.area = props.area_id;
        this.rule = form.dataset.rule;
        delete(props.area_id);
        const properties = Object.keys(props).reduce((acc, key) => {
            if (!/_mtype$/.test(key)) {
                const nKey = key.replace(/_/g, '-');
                acc[nKey] = props[key];
            }
            return acc;
        }, {});
        this.style[selector] = properties;
        this.setStyle(`style#style_${this.rule}`, this.getCSSTxt());
        this.hideWindow();
    }

    rgbToHex(color, fb = '#000000') {
        const hexRegex = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
        if (hexRegex.test(color)) {
            return color;
        }
        const rgbRegex = /^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*\d+\.?\d*)?\)$/;
        const match = color.match(rgbRegex);
        if (!match) {
            return fb;
        }
        const r = parseInt(match[1], 10);
        const g = parseInt(match[2], 10);
        const b = parseInt(match[3], 10);
        if (r < 0 || r > 255 || g < 0 || g > 255 || b < 0 || b > 255) {
            return fb;
        }
        const toHex = (value) => {
            const hex = value.toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }

    getCSSObj(styleSelector) {
        let cssText;
        if (/\{/.test(styleSelector)) {
            cssText = styleSelector;
        } else {
            const styleElement = document.querySelector(styleSelector);
            if (!styleElement || styleElement.tagName !== 'STYLE') {
              console.error('Elemento <style> não encontrado ou seletor inválido.');
              return null;
            }
            cssText = styleElement.textContent;
        }

        const cssObject = {};
        const rules = cssText.split('}');
      
        rules.forEach(rule => {
            rule = rule.trim();
            if (rule) {
                const [selector, properties] = rule.split('{');
                const cleanSelector = selector.trim();
                const selectorProperties = {};
                properties.split(';').forEach(property => {
                    property = property.trim();
                    if (property) {
                        const [key, value] = property.split(':');
                        const cleanKey = key.trim();
                        const cleanValue = value.trim();
                        selectorProperties[cleanKey] = cleanValue;
                    }
                });
                cssObject[cleanSelector] = selectorProperties;
            }
        });
      
        return cssObject;
    }
    
    setStyle(styleSelector, cssText) {
        const styleElement = document.querySelector(styleSelector);
        if (!styleElement || styleElement.tagName !== 'STYLE') {
          console.error('Elemento <style> não encontrado ou seletor inválido.');
          return null;
        }
        styleElement.textContent = cssText;
    }
    
    getCSSTxt(selector = '') {
        let cssText = '';
        if (selector) {
            cssText += `${selector} { `;
            const properties = this.style[selector];
            for (const key in properties) {
                if (properties.hasOwnProperty(key)) {
                    if (properties[key]) {
                        cssText += `${key}: ${properties[key]}; `;
                    }
                }
            }
            cssText += '} ';
            return cssText;
        }
        for (selector in this.style) {
            if (this.style.hasOwnProperty(selector)) {
                cssText += `${selector} { `;
                const properties = this.style[selector];
                for (const key in properties) {
                    if (properties.hasOwnProperty(key)) {
                        if (properties[key]) {
                            cssText += `${key}: ${properties[key]}; `;
                        }
                    }
                }
                cssText += '} ';
            }
        }
        return cssText;
    }
}