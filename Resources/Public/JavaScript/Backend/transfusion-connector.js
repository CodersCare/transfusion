/**
 * Module: @t3thi/transfusion/transfusion-connector.js
 * Provide actions to move items into the confirmed column and back
 */

class TransfusionConnectorActions {
  constructor() {
    var enableInputElements = function(inputElements, action){
      for (var i = 0; i < inputElements.length; i++) {
        if (!inputElements[i].classList.contains(action)) {
          continue;
        }
        inputElements[i].removeAttribute('disabled');
      }
    }
    var activateButton = function(button, action) {
      button.setAttribute('title', button.dataset.enabledtitle);
      button.classList.remove('btn-default');
      button.classList.add('btn-warning');
      var fullElement = button.closest('.t3-page-ce-wrapper');
      var inputElements = fullElement.getElementsByClassName(action);
      enableInputElements(inputElements, action)
    }
    var disableInputElements = function(inputElements, action){
      for (var i = 0; i < inputElements.length; i++) {
        if (!inputElements[i].classList.contains(action)) {
          continue;
        }
        inputElements[i].setAttribute('disabled', 'disabled');
      }
    }
    var deactivateButton = function(button, action) {
      button.setAttribute('title', button.dataset.disabledtitle);
      button.classList.add('btn-default');
      button.classList.remove('btn-warning');
      var fullElement = button.closest('.t3-page-ce-wrapper');
      var inputElements = fullElement.getElementsByClassName(action);
      disableInputElements(inputElements, action)
    }
    var moveOnceOrTwice = function(event) {
      event.preventDefault();
      if(typeof this.dataset.direction != "undefined" && typeof this.dataset.status != "undefined") {
        var direction = this.dataset.direction;
        var status = this.dataset.status;
        var action = this.dataset.action;
        var fullElement = this.closest('.t3-page-ce-wrapper');
        var inputElements = fullElement.getElementsByTagName('input');
        var parentCell = null;
        var targetCell = null;
        if (direction === 'left') {
          if (status === 'obvious') {
            targetCell = fullElement.closest('td').previousElementSibling;
          }
          if (status === 'possible') {
            targetCell = fullElement.closest('td').previousElementSibling.previousElementSibling;
          }
          if (status === 'broken' || status === 'orphaned') {
            targetCell = fullElement.closest('td').previousElementSibling.previousElementSibling.previousElementSibling;
          }
          if (status === 'orphaned' && action === 'new') {
            parentCell = fullElement.closest('td').previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling;
          }
          if (targetCell !== null) {
            if (targetCell.getElementsByClassName('t3-page-ce-wrapper').length) {
              alert('You can only connect one target element to each original record!');
            } else {
              targetCell.append(fullElement);
              enableInputElements(inputElements, 'change')
            }
          }
          if (parentCell !== null) {
            if (parentCell.getElementsByClassName('t3-page-ce-wrapper').length) {
              alert('You can only create one new parent element for each translated record!');
            } else {
              var parentElement = fullElement.cloneNode(true);
              var parentInputElements = parentElement.getElementsByTagName('input');
              parentCell.append(parentElement);
              enableInputElements(parentInputElements, 'new')
            }
          }
        }
        if (direction === 'right') {
          if (status === 'obvious') {
            targetCell = fullElement.closest('td').nextElementSibling;
          }
          if (status === 'possible') {
            targetCell = fullElement.closest('td').nextElementSibling.nextElementSibling;
          }
          if (status === 'broken' || status === 'orphaned') {
            targetCell = fullElement.closest('td').nextElementSibling.nextElementSibling.nextElementSibling;
          }
          if (targetCell !== null) {
            targetCell.append(fullElement);
            if (status === 'orphaned') {
              fullElement.closest('tr').getElementsByClassName('transfusion-original')[0].getElementsByClassName('t3-page-ce-wrapper')[0].remove();
            }
            disableInputElements(inputElements, 'change');
          }
        }
      }
    }

    var moveButtons = document.getElementsByClassName("btn-transfusion-selector");

    for (var i = 0; i < moveButtons.length; i++) {
      moveButtons[i].addEventListener("click", moveOnceOrTwice, false);
    }
    var markForDeletion = function(event) {
      event.preventDefault();
      var fullElement = this.closest('.t3-page-ce-wrapper');
      var deleteButton = fullElement.getElementsByClassName('delete')[0];
      var removeButton = fullElement.getElementsByClassName('btn-transfusion-remove')[0];
      if (deleteButton.getAttribute('disabled')==='disabled') {
        activateButton(this, 'delete');
        deactivateButton(removeButton, 'remove');
      } else {
        deactivateButton(this, 'delete');
      }
    }

    var deleteButtons = document.getElementsByClassName("btn-transfusion-delete");

    for (var i = 0; i < deleteButtons.length; i++) {
      deleteButtons[i].addEventListener("click", markForDeletion, false);
    }

    var removeAllConnections = function(event) {
      event.preventDefault();
      var fullElement = this.closest('.t3-page-ce-wrapper');
      var removeButton = fullElement.getElementsByClassName('remove')[0];
      var deleteButton = fullElement.getElementsByClassName('btn-transfusion-delete')[0];
      if (removeButton.getAttribute('disabled')==='disabled') {
        activateButton(this, 'remove');
        deactivateButton(deleteButton, 'delete');
      } else {
        deactivateButton(this, 'remove');
      }
    }

    var removeButtons = document.getElementsByClassName("btn-transfusion-remove");

    for (var i = 0; i < removeButtons.length; i++) {
      removeButtons[i].addEventListener("click", removeAllConnections, false);
    }

    var checkMarkedForRemovalOrDeletion = function(event) {
      event.preventDefault();
      var markedForRemoval = this.getElementsByClassName('btn-transfusion-remove btn-warning');
      var markedForDeletion = this.getElementsByClassName('btn-transfusion-delete btn-warning');
      if (markedForRemoval.length && markedForDeletion.length) {
        if (confirm('Are you sure you want to remove connections from or delete marked records?')) {
          this.submit();
        }
      } else if (markedForRemoval.length) {
        if (confirm('Are you sure you want to remove connections from marked records?')) {
          this.submit();
        }
      } else if (markedForDeletion.length) {
        if (confirm('Are you sure you want to delete marked records?')) {
          this.submit();
        }
      } else {
        this.submit();
      }
    }

    document.getElementById("TransfusionController").addEventListener('submit', checkMarkedForRemovalOrDeletion, false);

  }
}

export default new TransfusionConnectorActions();
