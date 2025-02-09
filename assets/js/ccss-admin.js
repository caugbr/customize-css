document.addEventListener('DOMContentLoaded', () => {
    applyClearInput();
    rootEvent('.formline .clear-input', 'click', event => {
        event.preventDefault();
        clearInput(event.target);
    });

    const tabEl = document.querySelector('.tabs');
    if (tabEl) {
        const tabs = tabEl.querySelectorAll('.tab-links a');
        Array.from(tabs).forEach(tab => {
            tab.addEventListener('click', evt => {
                evt.preventDefault();
                const name = evt.target.getAttribute('data-tab');
                tabEl.setAttribute('data-tab', name);
            });
        });
        observeTabChanges();
    }

    const addArea = $single('button.add-area');
    if (addArea) {
        addArea.addEventListener('click', event => {
            event.preventDefault();
            addEditableArea();
        });
        const addRImg = $single('button.add-image-replacement');
        addRImg.addEventListener('click', event => {
            event.preventDefault();
            addImageReplacement();
        });
        rootEvent('.prev-next button', 'click', event => {
            event.preventDefault();
            const tabs = $single('.tabs');
            const index = Number(tabs.getAttribute('data-tab'));
            const nindex = event.target.matches('.next-tab') ? index + 1 : index - 1;
            tabs.setAttribute('data-tab', nindex);
        });
        $apply('button.goto-add-area, button.goto-add-image', elem => {
            elem.addEventListener('click', event => {
                event.preventDefault();
                event.target.closest('.tab-content').classList.add('add');
            });
        });
        $apply('button.cancel-add-area, button.cancel-add-image', elem => {
            elem.addEventListener('click', event => {
                event.preventDefault();
                event.target.closest('.tab-content').classList.remove('add');
            });
        });

        let mediaUploader;
        rootEvent('a.open-image-layer', 'click', event => {
            event.preventDefault();
            const img = event.target.dataset.img;
            const openMediaUploader = (img) => {
                const title = img == 'original' ? ccssStr.chooseImage : ccssStr.chooseReplacement;
                if (mediaUploader) {
                    mediaUploader.state().set('title', title);
                    mediaUploader.off('select');
                    mediaUploader.on('select', () => handleImageSelect(img));
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media({
                    title,
                    button: { text: ccssStr.selectImage },
                    multiple: false
                });
                mediaUploader.on('select', () => handleImageSelect(img));
                mediaUploader.open();
            };
            const handleImageSelect = (img) => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                const line = $single(`a.open-image-layer[data-img="${img}"]`).closest('.formline');
                const dest = line.querySelector('input[type="text"]');
                dest.value = attachment.url;
            };
            openMediaUploader(img);
        });
    } else {
        return;
    }

    $apply('input[type="checkbox"][value="check_all"]', input => {
        input.addEventListener('input', event => {
            const input = event.target;
            const checks = input.closest('.formline').querySelectorAll('input[type="checkbox"]');
            Array.from(checks).forEach(check => {
                if (check.value != 'check_all') {
                    check.checked = input.checked;
                }
            });
        });
    });
    rootEvent('.area a.remove', 'click', event => {
        if (!confirm(ccssStr.askRemove)) {
            return;
        }
        event.preventDefault();
        const box = event.target.closest('.area');
        const selector = $single('.selector span', box).innerText.trim();
        removeEditableArea(selector);
    });
    rootEvent('.area a.edit', 'click', event => {
        event.preventDefault();
        const box = event.target.closest('.area');
        const selector = $single('.selector span', box).innerText.trim();
        editArea(selector);
    });

    
    rootEvent('.ri-wrapper a.remove', 'click', event => {
        if (!confirm(ccssStr.askRemove)) {
            return;
        }
        event.preventDefault();
        const box = event.target.closest('.ri-wrapper');
        const image = $single('img.original-image', box).src;
        removeImageReplacement(image);
    });
    rootEvent('.ri-wrapper a.edit', 'click', event => {
        event.preventDefault();
        const box = event.target.closest('.ri-wrapper');
        const image = $single('img.original-image', box).src;
        editImageReplacement(image);
    });

    $single('#rule_name').addEventListener('input', debounce(showReview, 300));
});

function showReview() {
    const postTypes = groupValues('ccss_post_types');
    const pages = selectValues($single('select#pages'));
    const places = groupValues('ccss_places');
    const taxonomies = {};
    $apply('select[data-tax]', sel => {
        const id = sel.name.replace('ccss_', '');
        taxonomies[id] = selectValues(sel);
    });
    const taxRelation = groupValues('ccss_tax_relation')[0];
    const roles = groupValues('ccss_roles');
    const postCustomField = $single('#post_custom_field').value;
    const postCustomValue = $single('#post_custom_value').value;
    const userCustomField = $single('#user_custom_field').value;
    const userCustomValue = $single('#user_custom_value').value;
    const users = selectValues($single('#users'));
    const areas = JSON.parse($single('#areas').value);
    const images = JSON.parse($single('#images').value);

    const obj = {
        postTypes, pages, places, taxonomies, taxRelation, roles, postCustomField, 
        postCustomValue, userCustomField, userCustomValue, users, areas, images
    };
    $single('#fullrule').value = JSON.stringify(obj);

    let enabled = true;
    let errMsgIds = [];
    let htm = `<strong>${ccssStr.postTypes}</strong>\n`;
    if (postTypes.length) {
        htm += `<em>${postTypes.join(', ')}</em>\n\n`;
    } else {
        htm += `<span class="err">${ccssStr.noPostTypes}</span>\n\n`;
    }
    htm += `<strong>${ccssStr.pages}</strong>\n`;
    if (pages.length) {
        htm += `<em>${pages.join(', ')}</em>\n\n`;
    } else {
        htm += `<span class="err">${ccssStr.noPages}</span>\n\n`;
    }
    htm += `<strong>${ccssStr.postCustomField}</strong>\n`;
    if (postCustomField) {
        htm += `<em>${postCustomField}</em>\n\n`;
    } else {
        htm += `<span class="err">${ccssStr.noPostCustomField}</span>\n\n`;
    }
    htm += `<strong>${ccssStr.postCustomFieldValue}</strong>\n`;
    if (postCustomValue) {
        htm += `<em>${postCustomValue}</em>\n\n`;
    } else {
        htm += `<span class="err">${ccssStr.noPostCustomValue}</span>\n\n`;
    }
    htm += `<strong>${ccssStr.places}</strong>\n`;
    if (places.length) {
        htm += `<em>${places.join(', ')}</em>\n\n`;
    } else {
        enabled = false;
        errMsgIds.push('noPlace');
        htm += `<span class="err">${ccssStr.noPlaces}</span>\n\n`;
    }

    htm += `<strong>${ccssStr.taxonomies}</strong>\n`;
    if (!isEmpty(taxonomies)) {
        for (const id in taxonomies) {
            const terms = taxonomies[id];
            if (terms.length) {
                htm += `    <strong>${id}</strong>\n`;
                htm += `    <em>${terms.join(', ')}</em>\n\n`;
            }
        }
    } else {
        if (0 == postTypes.length && 0 == pages.length && !postCustomField) {
            enabled = false;
            errMsgIds.push('noContent');
        }
        htm += `<span class="err">${ccssStr.noTaxonomies}</span>\n\n`;
    }

    htm += `    <strong>${ccssStr.taxRelation}</strong>\n`;
    htm += `    <em>${taxRelation}</em>\n\n`;

    htm += `<strong>${ccssStr.authUsers}</strong>\n`;
    htm += `    <strong>${ccssStr.roles}</strong>\n`;
    if (roles.length) {
        htm += `    <em>${roles.join(', ')}</em>\n\n`;
    } else {
        htm += `    <span class="err">${ccssStr.noRoles}</span>\n\n`;
    }
    htm += `    <strong>${ccssStr.userCustomField}</strong>\n`;
    if (userCustomField) {
        htm += `    <em>${userCustomField}</em>\n\n`;
    } else {
        htm += `    <span class="err">${ccssStr.noUserCustomField}</span>\n\n`;
    }
    htm += `    <strong>${ccssStr.userCustomFieldValue}</strong>\n`;
    if (postCustomValue) {
        htm += `    <em>${postCustomValue}</em>\n\n`;
    } else {
        htm += `    <span class="err">${ccssStr.noPostCustomValue}</span>\n\n`;
    }
    htm += `    <strong>${ccssStr.users}</strong>\n`;
    if (users.length) {
        htm += `    <em>${users.join(', ')}</em>\n\n`;
    } else {
        htm += `    <span class="err">${ccssStr.noUsers}</span>\n\n`;
    }

    if (!roles.length && !userCustomField && !users.length) {
        enabled = false;
        errMsgIds.push('noUser');
    }
    
    htm += `<strong>${ccssStr.areas}</strong>\n`;
    if (areas.length) {
        let selectors = [];
        areas.forEach(area => selectors.push(area.selector));
        htm += `<em>${selectors.join(', ')}</em>\n\n`;
    } else {
        enabled = false;
        errMsgIds.push('noAreas');
        htm += `<span class="err">${ccssStr.noAreas}</span>\n\n`;
    }
    
    htm += `<strong>${ccssStr.images}</strong>\n`;
    if (images.length) {
        let imgs = [];
        images.forEach(im => {
            imgs.push(`${im.image.split('/').pop()} &raquo; ${im.replacement.split('/').pop()}`);
        });
        htm += `<em>${imgs.join(', ')}</em>\n\n`;
    } else {
        htm += `<span class="err">${ccssStr.noImages}</span>\n\n`;
    }
    $single('.review-rule').innerHTML = htm;

    if (!$single('#rule_name').value) {
        enabled = false;
        errMsgIds.push('noName');
    }

    const err = $single('.error-messages');
    err.innerHTML = '';
    if (errMsgIds.length) {
        errMsgIds.forEach(msgId => {
            const msg = tag('div', {class: 'notice error'}, ccssStr[msgId]);
            err.appendChild(msg);
        });
    }

    if (enabled) {
        $single('#ccss_save,#ccss_update').removeAttribute('disabled');
    } else {
        $single('#ccss_save,#ccss_update').setAttribute('disabled', '');
    }
}

function removeEditableArea(selector) {
    const areas = JSON.parse($single('#areas').value);
    let narr = [];
    areas.forEach(area => {
        if (area.selector != selector) {
            narr.push(area);
        }
    });
    $single('#areas').value = JSON.stringify(narr, null, 4);
    showAreas();
}

function addEditableArea() {
    const info = editableAreaInfo();
    if (info === null) {
        return false;
    }
    if (getArea(info.selector)) {
        removeEditableArea(info.selector);
    }
    const rules = $single('#areas');
    let arr = JSON.parse(rules.value);
    arr = arr.filter(item => item !== 'check_all');
    info.id = arr.length ? Number(arr.at(-1).id) + 1 : 1;
    info.created = info.created ?? sqlDate();
    arr.push(info);
    rules.value = JSON.stringify(arr, null, 4);
    showAreas();
    $single('.areas-section').closest('.tab-content').classList.remove('add');
    scrollToTop();
}

function editableAreaInfo(clear = true) {
    const selector = $single('input#page_area');
    const description = $single('textarea#page_area_desc');
    let properties = groupValues('ccss_properties');
    const objProps = properties.reduce((acc, key) => { acc[key] = ''; return acc; }, {});
    if (selector.value && description.value && properties.length) {
        const obj = { selector: selector.value, description: description.value, properties: objProps };
        if (clear) {
            selector.value = '';
            description.value = '';
            $apply('[name="ccss_properties"]', input => input.checked = false);
        }
        return obj;
    }
    return null;
}

function showAreas() {
    const rules = $single('#areas');
    let arr = JSON.parse(rules.value);
    const wrap = $single('.areas-wrapper');
    const wrapper = tag('div', { class: 'editable-areas', 'data-empty': wrap.dataset.empty });
    arr.forEach(rule => {
        const remove = tag('a', { class: 'remove', href: '#' }, '<span class="dashicons dashicons-no"></span>');
        const edit = tag('a', { class: 'edit', href: '#' }, '<span class="dashicons dashicons-edit"></span>');
        const selector = tag('div', { class: 'selector' }, `<strong>${ccssStr.selector}:</strong> <span>${rule.selector}</span>`);
        const properties = tag('div', { class: 'properties' }, `<strong>${ccssStr.properties}:</strong> <span>${Object.keys(rule.properties).join(', ')}</span>`);
        const description = tag('div', { class: 'description' }, `<strong>${ccssStr.description}:</strong> <span>${rule.description}</span>`);
        const area = tag('div', { class: 'area' }, [edit, remove, selector, description, properties]);
        wrapper.appendChild(area);
    });
    wrap.innerHTML = '';
    wrap.appendChild(wrapper);
}

function getArea(selector) {
    const areas = JSON.parse($single('#areas').value);
    let area = null;
    areas.forEach(ar => {
        if (ar.selector == selector) {
            area = ar;
            return false;
        }
    });
    return area;
}

function editArea(selector) {
    let area = getArea(selector);
    if (area) {
        $single('#page_area').value = area.selector;
        $single('#page_area_desc').value = area.description;
        $apply('input[name="ccss_properties"]', check => {
            check.checked = area.properties[check.value] !== undefined ? true : false;
        });
        $single('.tabs').setAttribute('data-tab', 3);
        $single('.areas-section').closest('.tab-content').classList.add('add');
    }
}

function removeImageReplacement(image) {
    const images = JSON.parse($single('#images').value);
    let narr = [];
    images.forEach(img => {
        if (img.image != image) {
            narr.push(img);
        }
    });
    $single('#images').value = JSON.stringify(narr, null, 4);
    showImageReplacements();
}

function addImageReplacement() {
    const info = imageReplacementInfo();
    if (info === null) {
        return false;
    }
    if (getImageReplacement(info.image)) {
        removeImageReplacement(info.image);
    }
    const images = $single('#images');
    let arr = JSON.parse(images.value);
    info.id = arr.length ? Number(arr.at(-1).id) + 1 : 1;
    info.created = info.created ?? sqlDate();
    arr.push(info);
    images.value = JSON.stringify(arr, null, 4);
    showImageReplacements();
    $single('.images-section').closest('.tab-content').classList.remove('add');
    scrollToTop();
}

function imageReplacementInfo(clear = true) {
    const image = $single('input#image_url');
    const replacement = $single('input#image_replacement');
    if (image.value) {
        const obj = { image: image.value, replacement: replacement.value };
        if (clear) {
            image.value = '';
            replacement.value = '';
        }
        return obj;
    }
    return null;
}

function showImageReplacements() {
    const images = $single('#images');
    let arr = JSON.parse(images.value) || [];
    const wrap = $single('.images-wrapper');
    const wrapper = tag('div', { class: 'image-replacements', 'data-empty': wrap.dataset.empty });
    arr.forEach(rimg => {
        const remove = tag('a', { class: 'remove', href: '#' }, '<span class="dashicons dashicons-no"></span>');
        const edit = tag('a', { class: 'edit', href: '#' }, '<span class="dashicons dashicons-edit"></span>');
        const image = tag('img', { class: 'original-image', src: rimg.image });
        const cols = tag('div', { class: 'cols' }, '<div class="col img"></div><div class="gap">&raquo;</div><div class="col rpl"></div>');
        cols.querySelector('.col.img').appendChild(image);
        if (rimg.replacement) {
            const replacement = tag('img', { class: 'replacement-image', src: rimg.replacement });
            cols.querySelector('.col.rpl').appendChild(replacement);
        }
        const wrap = tag('div', { class: 'ri-wrapper' }, [edit, remove, cols]);
        wrapper.appendChild(wrap);
    });
    wrap.innerHTML = '';
    wrap.appendChild(wrapper);
}

function getImageReplacement(image) {
    const images = JSON.parse($single('#images').value);
    let img = null;
    images.forEach(ar => {
        if (ar.image == image) {
            img = ar;
            return false;
        }
    });
    return img;
}

function editImageReplacement(image) {
    let img = getImageReplacement(image);
    if (img) {
        $single('#image_url').value = img.image;
        $single('#image_replacement').value = img.replacement;
        $single('.tabs').setAttribute('data-tab', 4);
        $single('.images-section').closest('.tab-content').classList.add('add');
    }
}

function groupValues(name, exclude = 'check_all') {
    let values = [];
    $apply(`[name="${name}"]`, input => {
        if (input.checked && input.value != exclude) {
            values.push(input.value);
        }
    });
    return values;
}

function selectValues(select) {
    return Array.from(select.selectedOptions).map(option => option.value);
}

function applyClearInput() {
    const lines = $list('.formline');
    Array.from(lines).forEach(line => {
        const inputs = $list('input:not([type="hidden"]):not(.other *), select, textarea:not(.other *)', line);
        let skip = false;
        Array.from(inputs).forEach(inp => {
            if (inp.matches('.no-clear')) {
                skip = true;
                return false;
            }
        });
        if (skip) {
            return true;
        }
        const icon = '<span class="dashicons dashicons-remove"></span>';
        const lnk = tag('a', {href: '#', class: 'clear-input', title: 'Limpar'}, icon);
        if (inputs.length == 1) {
            if (/textarea|select/i.test(inputs[0].tagName) || 
                /text|url|email|tel|search|password|number|color/.test(inputs[0].type)) {
                insertAfter(lnk, inputs[0]);
            }
        } 
        if (inputs.length > 1) {
            lnk.classList.add('multi');
            line.appendChild(lnk);
        }
    });
}

function clearInput(btn) {
    const line = btn.closest('.formline');
    const check = $single('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea', line);
    if (check) {
        check.value = '';
        check.dispatchEvent(new Event('input'));
        check.dispatchEvent(new Event('change'));
    }
    const checks = $list('input[type="checkbox"],input[type="radio"]', line);
    if (checks.length) {
        Array.from(checks).forEach(elem => {
            elem.checked = false;
            elem.dispatchEvent(new Event('input'));
            elem.dispatchEvent(new Event('change'));
        });
    }
}

function observeTabChanges() {
    const tabsElement = document.querySelector('.tabs');
    if (!tabsElement) {
        console.error('Elemento ".tabs" não encontrado!');
        return;
    }
    const callback = (mutationsList) => {
        mutationsList.forEach(mutation => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-tab') {
                const newTab = tabsElement.getAttribute('data-tab');
                onTabChange(newTab);
            }
        });
    };
    const observer = new MutationObserver(callback);
    observer.observe(tabsElement, {
        attributes: true,
        attributeFilter: ['data-tab'],
    });
}

function onTabChange(newTab) {
    if (newTab == 5) {
        showReview();
    }
    if (newTab == 3) {
        showAreas();
    }
    if (newTab == 4) {
        showImageReplacements();
    }
    scrollToTop();
}

function isEmpty(obj) {
    if (Object.keys(obj).length === 0) {
        return true;
    }
    return Object.values(obj).every(value => Array.isArray(value) && value.length === 0);
}

function debounce(func, delay) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => func.apply(this, args), delay);
    };
}

function sqlDate(date = null) {
    date = date ? date : (new Date());
    const pad = (num) => String(num).padStart(2, '0');
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());
    const seconds = pad(date.getSeconds());
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}
  
function scrollToTop(top = 0) {
    window.scrollTo({ top, behavior: 'smooth' });
}