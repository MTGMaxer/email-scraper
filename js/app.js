window.addEventListener('DOMContentLoaded', () => {
    main();
});

function main() {
    let scrapingActive = false;
    const scrapButton = document.getElementById('scrap-btn');
    const urlInput = document.getElementById('url-input');
    const loader = document.getElementById('loader');
    scrapButton.addEventListener('click', () => {
        if (scrapingActive === false) {
            loader.style.setProperty('display', 'inline-block');
            scrapingActive = true;
            scrap(urlInput.value).finally(() => {
                scrapingActive = false;
                loader.style.setProperty('display', 'none');
            }
            )
                ;
        }
    });
    urlInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') {
            if (scrapingActive === false) {
                loader.style.setProperty('display', 'inline-block');
                scrapingActive = true;
                scrap(urlInput.value).finally(() => {
                    scrapingActive = false;
                    loader.style.setProperty('display', 'none');
                });
            }
        }
    });
}

async function scrap(url) {
    const resultContainer = document.getElementById('result-container');
    resultContainer.innerHTML = '';
    let result = await fetch(`/scrap.php?url=${url}`);
    let jsonResult = await result.json();
    console.log(jsonResult);
    let treeRoot = document.createElement('details');
    let allEmails = document.createElement('p');
    allEmails.innerText = `All e-mails found: ${jsonResult.allEmails.join(', ')}`;
    addUrlTree(jsonResult, treeRoot);
    resultContainer.append(treeRoot);
    resultContainer.append(allEmails);

}

function addUrlTree(resultUrlObj, detailsElement) {
    let { url, emails, urls } = resultUrlObj;
    let summary = document.createElement('summary');
    summary.innerText = url;
    detailsElement.append(summary);

    if (Array.isArray(emails) && emails.length > 0) {
        let foundEmails = document.createElement('p');
        foundEmails.innerText = `Found e-mails: ${emails.join(', ')}`;
        detailsElement.append(foundEmails);
    }

    if (Array.isArray(urls)) {
        urls.forEach((url) => {
            let newDetails = document.createElement('details');
            addUrlTree(url, newDetails);
            detailsElement.append(newDetails);
        });
    }
}
