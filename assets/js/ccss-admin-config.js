document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".position").forEach(positionField => {
        const hiddenInput = positionField.querySelector("input[type=hidden]");
        const selectX = positionField.querySelector("select[id$='_prop_x']");
        const inputX = positionField.querySelector("input[id$='_x']");
        const selectY = positionField.querySelector("select[id$='_prop_y']");
        const inputY = positionField.querySelector("input[id$='_y']");

        // Lê o valor atual do input hidden e aplica aos campos
        if (hiddenInput.value) {
            const matches = hiddenInput.value.match(/(top|bottom|left|right):\s*(\d+)px;/g);
            if (matches) {
                matches.forEach(rule => {
                    let [prop, value] = rule.split(":");
                    prop = prop.trim();
                    value = parseInt(value, 10);

                    if (prop === "top" || prop === "bottom") {
                        selectY.value = prop;
                        inputY.value = value;
                    } else if (prop === "left" || prop === "right") {
                        selectX.value = prop;
                        inputX.value = value;
                    }
                });
            }
        }

        // Atualiza o input hidden ao alterar os valores
        const updateHiddenInput = () => {
            hiddenInput.value = `${selectY.value}: ${inputY.value}px; ${selectX.value}: ${inputX.value}px;`;
        };

        selectX.addEventListener("change", updateHiddenInput);
        inputX.addEventListener("input", updateHiddenInput);
        selectY.addEventListener("change", updateHiddenInput);
        inputY.addEventListener("input", updateHiddenInput);
    });
});
