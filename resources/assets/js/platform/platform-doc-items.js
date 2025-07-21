'use strict';

let PdfOverview = function (element) {
  if(!element) {
    throw Error('Not valid element');
  }

  this.element = (element instanceof $) ? element : $(element);
};

PdfOverview.prototype.load = function () {
  if(this.element.length <= 0) {
    throw Error('Invalid element');
  }

  let $this = this;
  // Compute
  let contentItems = this.element.find('div.ad-wcontent canvas').toArray().filter(function(element) {
    let wantedAttr = $(element).attr('data-bs-doc');
    return wantedAttr !== undefined && wantedAttr !== null && wantedAttr.length > 0;
  });

  // Bind event
  let dwTitles = this.element.find('.ad-wtitle');
  console.dir(dwTitles);
  if(dwTitles.length > 0) {
    dwTitles.bind('click', function(event) {
      event.preventDefault();
      let urlTarget = $(this).attr('data-bs-target');

      if(urlTarget !== undefined && urlTarget.length > 0) {
        window.open(urlTarget, '_blank');
      }
    })
  }

  contentItems.forEach(function(canvas) {
    $this.showOverview($(canvas).attr('data-bs-doc'), $(canvas));
  });
}

PdfOverview.prototype.showOverview = async function (url, render) {
  // Initialize and load the PDF and get handle of pdf document
  let pdfDoc;

  try {
    console.dir('Loading from :: ' + url);
    pdfDoc = await pdfjsLib.getDocument({url: url});
  } catch (error) {
    console.dir(error.message);
  }
  console.dir('Load ended ...');

  // Show the first page for simple overview
  await this.showOverviewPage(pdfDoc, render, 1);
}

PdfOverview.prototype.showOverviewPage = async function (pdfDoc, render, pageNo) {
  // Load and render specific page of the PDF
  let renderingInProgress = 1;
  let page;

  // Get handle of page
  try {
    page = await pdfDoc.getPage(pageNo);
  } catch (error) {
    console.dir(error.message);
  }

  // Original width of the pdf page at scale 1
  let pdfOriginalWidth = page.getViewport(1).width;

  // As the canvas is of a fixed width we need to adjust the scale of the viewport where page is rendered
  let scaleRequired = (render.width() / pdfOriginalWidth);

  // Get viewport to render the page at required scale
  let viewport = page.getViewport(scaleRequired);

  // // Set canvas height same as viewport height
  // docCanvas.height = viewport.height;

  // Page is rendered on <canvas> element
  let renderContext = {
    canvasContext: render[0].getContext('2d'),
    viewport: viewport
  };

  // Render the page contents in the canvas
  try {
    await page.render(renderContext);
  } catch (error) {
    console.dir(error.message);
  }

  renderingInProgress = 0;
}
