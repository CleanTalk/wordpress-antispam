const loaderContainer = document.createElement('div');

document.addEventListener('DOMContentLoaded', function() {
    loaderContainer.className = 'ct-browser-check-container';
    let startTime = null;
    let timeoutToShowResult = 3000;

    document.addEventListener('ctBotDetectorStart', function() {
        loaderContainer.style.display = 'block';
        startTime = new Date();
    });

    document.addEventListener('ctBotDetectorEnd', function() {
        console.log('ctBotDetectorEnd');
    });

    document.addEventListener('ctBotDetectorResult', function() {
        if (startTime && new Date() - startTime < timeoutToShowResult) {
            setTimeout(() => {
                showResult();
            }, timeoutToShowResult - (new Date() - startTime));
            return;
        }
        setTimeout(() => {
            showResult();
        }, timeoutToShowResult);
    });

    function showResult() {
        let results = ['bot', 'human'];
        let result = results[Math.floor(Math.random() * results.length)];

        document.querySelector('.ct-browser-check-loader').remove();
        document.querySelector('.ct-browser-check-description').remove();
        document.querySelector('.ct-browser-check-container').style.height = '50px';

        // prepare data
        let title = 'botDetector';
        let svg = '';
        let arrowAction = '';
        switch (result) {
            case 'bot':
                console.log('bot');
                // red cross
                svg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="#FF0000"/></svg>';
                break;
            case 'human':
                console.log('human');
                title = 'The Real Person';
                svg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#4CAF50"/></svg>';
                arrowAction = ' style="cursor: pointer;" onclick="ctBrowserCheckHide();" ';
                break;
        }
        document.querySelector('.ct-browser-check-title').textContent = title;
        const dev = document.createElement('div');
        dev.className = 'ct-browser-check-human';
        dev.innerHTML = `
            ${svg}
            <span class="ct-browser-check-human-arrow"${arrowAction}>&gt;</span>
        `;
        loaderContainer.appendChild(dev);
    }


    document.addEventListener('ctBotDetectorError', function() {
        console.log('ctBotDetectorError');
    });

    console.log('DOMContentLoaded');
    
    // Create and add styles
    const style = document.createElement('style');
    style.textContent = `
        .ct-browser-check-loader { 
            width: 20px; 
            margin: 5px auto;
            aspect-ratio: 1; 
            border: 3px solid #F3F6F9; 
            border-radius: 50%; 
            position: relative; 
            transform: rotate(45deg); 
        } 
        .ct-browser-check-loader::before { 
            content: ""; 
            position: absolute; 
            inset: -3px; 
            border-radius: 50%; 
            border: 3px solid #026E88; 
            animation: l18 2s infinite linear; 
        } 
        @keyframes l18 { 
            0% {clip-path:polygon(50% 50%,0 0,0 0,0 0 ,0 0 ,0 0 )} 
            25% {clip-path:polygon(50% 50%,0 0,100% 0,100% 0 ,100% 0 ,100% 0 )} 
            50% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,100% 100%,100% 100%)} 
            75% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 100%)} 
            100% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 0 )} 
        }
        .ct-browser-check-human {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .ct-browser-check-human svg {
            width: 20px;
            height: 20px;
        }
        .ct-browser-check-human-arrow {
            font-size: 20px;
            font-weight: bold;
            color: #777777;
            padding-left: 15px;
            margin-bottom: 2px;
        }
    `;
    document.head.appendChild(style);

    // Create loader container
    loaderContainer.style.cssText = `
        display: none;
        font-size: 12px;
        font-weight: bold;
        color: #777777;
        text-align: center;
        position: fixed;
        top: 20%;
        right: -5px;
        width: 110px;
        height: 80px;
        z-index: 9999;
        background-color: #fff;
        border-radius: 5px 0 0 5px;
        border: 1px solid #bbbbbb;
        box-shadow: 0 0 5px 0 rgba(0, 0, 0, 0.1);
        transition: right 0.3s ease-in-out;
    `;

    // add div with title
    const title = document.createElement('div');
    title.className = 'ct-browser-check-title';
    title.style.cssText = `
        border-bottom: 1px solid #bbbbbb;
    `;
    title.textContent = 'botDetector';
    loaderContainer.appendChild(title);
    
    // Create loader element
    const loader = document.createElement('div');
    loader.className = 'ct-browser-check-loader';

    // Add loader to container and container to body
    loaderContainer.appendChild(loader);
    document.body.appendChild(loaderContainer);

    // add div with description
    const description = document.createElement('div');
    description.className = 'ct-browser-check-description';
    description.style.cssText = `
        border-top: 1px solid #bbbbbb;
    `;
    description.textContent = 'Browser check';
    loaderContainer.appendChild(description);
});

function ctBrowserCheckHide() {
    document.querySelector('.ct-browser-check-title').textContent = 'TRP';
    loaderContainer.style.right = '-70px';
    loaderContainer.style.alignItems = 'left';
    document.querySelector('.ct-browser-check-title').style.alignItems = 'left';
    document.querySelector('.ct-browser-check-title').style.width = '40px';
    document.querySelector('.ct-browser-check-human').style.marginLeft = '-30px';
}
