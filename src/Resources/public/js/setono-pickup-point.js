let pickupPoints = {
  pickupPointShippingMethods: document.querySelectorAll('input.input-shipping-method[data-pickup-point-provider]'),
  pickupPointsField: document.querySelectorAll('div.setono-sylius-pickup-point-field')[0],
  pickupPointsFieldInput: document.querySelectorAll('div.setono-sylius-pickup-point-field > input.setono-sylius-pickup-point-field-input')[0],
  pickupPointsFieldChoices: document.querySelectorAll('div.setono-sylius-pickup-point-field-choices')[0],
  pickupPointsFieldChoicePrototype: document.querySelectorAll('div.setono-sylius-pickup-point-field-choice-prototype')[0],
  shippingMethods: document.querySelectorAll('input.input-shipping-method'),
  pickupPointChoices: {},
  lastChosenPickupPointId: null,
  init: function (args) {
    self = this;
    self.searchUrl = args.searchUrl;

    //complete the label info
    if (self.pickupPointLabel.length > 0) {
      self.completePickupPointLabel();
      return;
    }

    if (0 === self.pickupPointShippingMethods.length) {
      return;
    }

    self.pickupPointShippingMethods.forEach(function (element) {
      self.searchAndStorePickupPoints(element);
    });

    self.shippingMethods.forEach(function (element) {
      element.addEventListener('change', function () {
        if (0 !== self.pickupPointsFieldInput.value.length) {
          self.lastChosenPickupPointId = self.pickupPointsFieldInput.value;
        }
        self.pickupPointsFieldInput.value = null;
        self.render();
      });
    });

    self.render();
  },
  searchAndStorePickupPoints: function (input) {
    let shippingMethodCode = input.getAttribute('value');
    self.pickupPointChoices[shippingMethodCode] = {};

    let pickupPointChoices = this.pickupPointChoices;
    let inputSearchUrl = this.searchUrl;
    inputSearchUrl = inputSearchUrl.replace('{providerCode}', input.getAttribute('data-pickup-point-provider'));
    inputSearchUrl = inputSearchUrl.replace('{_csrf_token}', input.getAttribute('data-csrf-token'));

    const xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
      if (4 === xhttp.readyState && 200 === xhttp.status) {
        pickupPointChoices[shippingMethodCode] = JSON.parse(xhttp.response);
      }
    }
    // Use synchronous xhttp request since we need the result to continue the process
    // @todo Convert to async as synchronous requests deprecated by browsers
    xhttp.open('GET', inputSearchUrl, false);
    xhttp.send();

    self.pickupPointChoices = pickupPointChoices;
  },
  completePickupPointLabel: function () {
    console.log('completePickupPointLabel');
    const element = self.pickupPointLabel[0];
    const getUrl = element.getAttribute('data-url')
    const xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
      if (4 === xhttp.readyState && 200 === xhttp.status) {
        const pickupPoint = JSON.parse(xhttp.response);
        element.innerHTML += `${pickupPoint.name}  ${ self.getIdentifier(pickupPoint)}`;
      }
    }

    // @todo Convert to async as synchronous requests deprecated by browsers
    xhttp.open('GET', getUrl, false);
    xhttp.send();
  },
  render: function () {
    let selectedElement = document.querySelectorAll('input.input-shipping-method:checked');
    selectedElement = selectedElement[0];
    let currentShippingMethodCode = selectedElement.getAttribute('value');

    const values = self.pickupPointChoices[currentShippingMethodCode];
    if (undefined === values || undefined === values.length || 0 === values.length) {
      self.pickupPointsField.style.display = 'none';
      self.pickupPointsFieldChoices.innerHTML = '';
      return;
    }

    self.pickupPointsField.style.display = 'block';
    self.pickupPointsFieldChoices.innerHTML = self.valuesToRadio(values);

    var currentPickupPointId = self.pickupPointsFieldInput.value;
    if (null === currentPickupPointId || 0 === currentPickupPointId.length) {
      currentPickupPointId = self.lastChosenPickupPointId;
    }

    var currentPickupPointRadio = document.querySelector(`input.setono-sylius-pickup-point-field-choice-field[value="${currentPickupPointId}"]`);
    if (null !== currentPickupPointRadio) {
      currentPickupPointRadio.checked = true;
    }

    const choices = document.querySelectorAll('input.setono-sylius-pickup-point-field-choice-field');
    choices.forEach(function (choice) {
      choice.addEventListener('change', function () {
        self.pickupPointsFieldInput.value = choice.getAttribute('value');
      });
    });
  },
  valuesToRadio(values) {
    let content = ``;

    values.forEach(function (value) {
      let prototype = self.pickupPointsFieldChoicePrototype.innerHTML;
      let radio = prototype.replace(/{code}/g, value.code);
      radio = radio.replace(/{name}/g, value.name);
      radio = radio.replace(/{full_address}/g, value.full_address);
      radio = radio.replace(/{latitude}/g, value.latitude);
      radio = radio.replace(/{longitude}/g, value.longitude);

      let distance = value.distance !== undefined ? value.distance + 'm' : '';
      radio = radio.replace(/{distance}/g, distance);

      let opening_hours = '';
      if(value.opening_hours !== undefined) {
        for (const [key, day] of Object.entries(value.opening_hours)) {
          opening_hours = `${opening_hours} <br> ${day}`;
        }
      }
      radio = radio.replace(/{opening_hours}/g, opening_hours);
      radio = radio.replace(/{identifier}/g, self.getIdentifier(value));

      content += radio;
    });

    return content;
  },
  getIdentifier(value) {
    const code = value.code.split(value.code_delimiter);
    let id = code[1];
    let identifier = id;
    // DPD for the URL info (FR)
    if (code[0] === 'dpd' && code[2] === 'FR') {
      identifier = `<a href="https://www.dpd.fr/dpdrelais/id_${id}" target="_blank"> ${id} <i class="external alternate icon"></i></a>`;
    }

    return identifier;
  },
};
