/**
 * @file
 * Javascript for the Views Photo Grid module.
 */

(function ($, Drupal, drupalSettings) {

  "use strict";
  Drupal.viewsPhotoGrid = {};
  Drupal.behaviors.viewsPhotoGrid = {};

  /**
   * Class representing a photo grid.
   */
  Drupal.viewsPhotoGrid.grid = class {
    gridId;
    width;
    padding;
    maxHeight;
    rows = [];

    /**
     * Constructor for the grid object.
     *
     * @param gridId
     *   The id of the grid. Needs to match the id attribute of the container
     *   element. See arrangeGrid().
     * @param width
     *   The total width of the grid that the each row must fit.
     * @param padding
     *   Padding between items in pixels.
     */
    constructor(gridId, width, padding, maxHeight) {
      this.gridId = gridId;
      this.width = width;
      this.padding = (typeof padding !== 'undefined' ? parseInt(padding) : 1);
      this.maxHeight = (typeof maxHeight !== 'undefined' ? parseInt(maxHeight) : 0);
    }

    /**
     * Creates a new row and adds it to the grid object.
     *
     * @returns {Drupal.viewsPhotoGrid.gridRow}
     *   The new row.
     */
    createRow() {
      let rowId = this.rows.length;
      let row = new Drupal.viewsPhotoGrid.gridRow(rowId, this.width, this.padding, this.maxHeight);
      this.rows.push(row);
      return row;
    }

    /**
     * Renders the grid.
     */
    render() {
      // Keeps track of the current vertical position.
      let currentPos = 0;

      // Iterate through all rows, and let them render at the current
      // vertical position.
      for (let i = 0; i < this.rows.length; i++) {
        this.rows[i].render(currentPos);

        currentPos += this.rows[i].height + this.padding;
      }

      // Set the height on the container
      $('#views-photo-grid-' + this.gridId).css('height', currentPos + 'px');
    }
  };

  /**
   * Class representing a grid row.
   */
  Drupal.viewsPhotoGrid.gridRow = class {
    rowId;
    width;
    padding;
    maxHeight;
    height = 0;
    usedWidth = 0;
    items = [];

    /**
     * Constructor for the row object.
     *
     * @param rowId
     *   The row's id. Added to the dom elements for identification purposes.
     * @param width
     *   The total width of the row that the row items must fit.
     * @param padding
     *   Padding between items in pixels.
     */
    constructor(rowId, width, padding, maxHeight) {
      this.rowId = rowId;
      this.width = width;
      this.padding = (typeof padding !== 'undefined' ? padding : 1);
      this.maxHeight = (typeof maxHeight !== 'undefined' ? maxHeight : 1);
    }

    /**
     * Creates and adds an item to the row. Keeps track of the width used by
     * the item.
     *
     * @param itemId
     *   The id of the item. Needs to match the id attribute of the container
     *   element. See arrangeGrid().
     * @param width
     *   The original width of the image contained in this grid item.
     * @param height
     *   The original height of the image contained in this grid item.
     */
    createItem(itemId, width, height) {
      let item = new Drupal.viewsPhotoGrid.gridItem(itemId);
      item.width = width;
      item.height = height;
      item.displayWidth = width;
      item.displayHeight = height;

      // Place item into the row.
      if ((item.displayHeight && item.displayHeight < this.height) || !this.height) {
        // This item is smaller than the current row height.
        // Need to shrink the row to avoid upscaling.
        this.items.push(item);
        this.adjustRowHeight(item.displayHeight);
      }
      else {
        // Resize the item to match the row height.
        item = this.fitItem(item);
        this.items.push(item);
      }

      this.usedWidth = this.usedWidth + item.displayWidth;
    }

    /**
     * Checks if there is available space in the row for further items.
     */
    isFull() {
      return (this.getAvailableWidth() <= 0);
    }

    /**
     * Returns the width available for additional items to be added.
     *
     * @returns
     *   Width in pixels.
     */
    getAvailableWidth() {
      return this.width - this.usedWidth;
    }

    /**
     * Modifies an item's display size so that its height fits the row.
     *
     * @param item
     *   Item object.
     * @returns
     *   The modified item object.
     */
    fitItem(item) {
      if (!item.width || !item.height) {
        return item;
      }

      let aspect = item.width / item.height;
      let newWidth = Math.round(aspect * this.height);

      item.displayWidth = newWidth;
      item.displayHeight = this.height;

      return item;
    }

    /**
     * Sets the row's height, and adjusts all items currently in the row
     * to fit the new height.
     *
     * @param newHeight
     *   New height in pixels.
     */
    adjustRowHeight(newHeight) {
      if (this.maxHeight && this.maxHeight < newHeight) {
        newHeight = this.maxHeight;
      }
      this.height = newHeight;

      // Iterate through existing items and set the height while maintaining
      // the aspect ratio.
      this.usedWidth = 0;
      for (let i = 0; i < this.items.length; i++) {
        this.fitItem(this.items[i]);
        this.usedWidth = this.usedWidth + this.items[i].displayWidth;
      }
    }

    /**
     * Renders the row. Applies CSS to the items in the row.
     */
    render(top) {
      if (this.items.length == 0) {
        // There isn't anything to render.
        return;
      }

      // Calculate how much space is available for row items when accounting
      // for padding.
      let targetWidth = this.width - (this.items.length - 1) * this.padding;
      if (targetWidth <= 0) {
        // There is no room for any items.
        return;
      }

      // All items will be resized by a certain percentage to make them fit
      // the width calculated above.
      let adjustment;
      if (targetWidth < this.usedWidth) {
        adjustment = targetWidth / this.usedWidth;
      }
      else {
        // Content would need to be enlarged to fit. Leave as is.
        adjustment = 1;
      }
      this.height = Math.round(this.height * adjustment);

      // Keeps track of the X position where the next item
      // should be placed.
      let currentPos = 0;

      // Adjust widths so that the items fully fill in the full width.
      // Apply css to place items.
      for (let i = 0; i < this.items.length; i++) {

        // Adjust size to fit the width, if needed.
        if (adjustment != 1) {
          this.items[i].displayHeight = this.height;

          if (i < this.items.length - 1) {
            this.items[i].displayWidth = Math.round(this.items[i].displayWidth * adjustment);
          }
          else {
            // The last item should use up all the space that's left. This will
            // fix the discrepancy caused by rounding.
            this.items[i].displayWidth = this.width - currentPos;
          }
        }

        // Apply placement.
        let elem = $('#views-photo-grid-' + this.items[i].itemId);
        elem.attr('data-row-id', this.rowId);
        elem.css('top', top + 'px');
        elem.css('left', currentPos + 'px');
        elem.find('img').css('width', this.items[i].displayWidth + 'px');
        elem.find('img').css('height', this.items[i].displayHeight + 'px');

        currentPos += this.items[i].displayWidth + this.padding;
      }
    }
  };

  /**
   * Class representing a grid item.
   */
  Drupal.viewsPhotoGrid.gridItem = class {
    itemId;
    width = 0;
    height = 0;
    displayWidth = 0;
    displayHeight = 0;

    /**
     * Constructor for the row item object.
     *
     * @param itemId
     *   The item's id.
     */
    constructor(itemId) {
      this.itemId = itemId;
    }

  };

  /**
   *  Arranges the grid.
   */
  Drupal.behaviors.viewsPhotoGrid.arrangeGrid = function () {
    // Find all instances of this view style.
    $('.views-photo-grid-container').each(function (containerIndex) {
      let container = $(this);
      let containerWidth = container.width();

      // Create a unique id for this grid container.
      $(this).attr('id', 'views-photo-grid-' + containerIndex);

      // Create grid objects.
      let grid = new Drupal.viewsPhotoGrid.grid(containerIndex, containerWidth, drupalSettings.viewsPhotoGrid.gridPadding, drupalSettings.viewsPhotoGrid.maxHeight);
      let row = grid.createRow();

      // Find grid items and create rows.
      container.find('.views-photo-grid-item').each(function (itemIndex) {
        // Create a unique id for this grid item.
        let itemId = containerIndex + '-' + itemIndex;
        $(this).attr('id', 'views-photo-grid-' + itemId);

        let img = $(this).find('img');

        // Remove css so that the actual size can be determined.
        $(this).find('img').css('height', '');
        $(this).find('img').css('width', '');

        row.createItem(itemId, img.width(), img.height());

        // Check if adding this item has used up all the space.
        if (row.isFull()) {
          // This item is the last one that fits the container.
          // Start a new row.
          row = grid.createRow();
        }

      }); // container.find('.views-photo-grid-item').each()

      // Render.
      grid.render();

    });
  };

  /**
   * Returns a function that can be used as an event handler.
   * The function triggers arrangeGrid() and ensures that it's not triggered
   * too frequently.
   *
   * @returns {Function}
   *   Event handler.
   */
  Drupal.behaviors.viewsPhotoGrid.getTriggerHandler = function () {
    let timer;

    return function () {
      if (timer) {
        clearTimeout(timer);
      }

      timer = setTimeout(function () {
        Drupal.behaviors.viewsPhotoGrid.arrangeGrid();
      }, 50);
    };
  };

  /**
   * Attaches behaviors.
   */
  Drupal.behaviors.viewsPhotoGrid.attach = function (context, settings) {
    let triggerHandler = this.getTriggerHandler();

    // Arrange grid items.
    this.arrangeGrid();

    // Attach event listeners to trigger grid rearrangement when necessary.
    $('.views-photo-grid-item img').bind('load', triggerHandler);
    $(window).bind('resize', triggerHandler);
  };

})(jQuery, Drupal, drupalSettings);
