import "./styles/widget.scss";

const prefix = "user-feedback-widget";

// https://developer.mozilla.org/en-US/docs/Web/API/Screen_Capture_API/Using_Screen_Capture
const displayMediaOptions = {
  video: {
    displaySurface: "browser",
  },
  audio: {
    suppressLocalAudioPlayback: false,
  },
  preferCurrentTab: false,
  selfBrowserSurface: "exclude",
  systemAudio: "include",
  surfaceSwitching: "include",
  monitorTypeSurfaces: "include",
};

function startCapture(displayMediaOptions) {
  return navigator.mediaDevices
    .getDisplayMedia(displayMediaOptions)
    .catch((err) => {
      console.error(err);
      return null;
    });
}

addEventListener("load", () => {
  const getElement = (id) =>
    document.getElementById(prefix + (id ? "-" + id : ""));

  const container = getElement();
  const config = JSON.parse(container.dataset.config ?? "null");

  const form = container.shadowRoot.querySelector("form");

  // https://developer.mozilla.org/en-US/docs/Web/API/Screen_Capture_API/Using_Screen_Capture
  async function captureScreenshot(data, name) {
    const stream = await navigator.mediaDevices.getDisplayMedia({
      preferCurrentTab: true,
    });

    const vid = document.createElement("video");

    const promise = new Promise((resolve) => {
      vid.addEventListener("loadedmetadata", async function () {
        const canvas = document.createElement("canvas"),
          ctx = canvas.getContext("2d");
        ctx.canvas.width = vid.videoWidth;
        ctx.canvas.height = vid.videoHeight;
        ctx.drawImage(vid, 0, 0, vid.videoWidth, vid.videoHeight);

        vid.srcObject.getTracks().forEach((track) => track.stop());
        vid.srcObject = null;

        const screenshot = canvas.toDataURL("image/png");
        // data.set(name, screenshot)

        await canvas.toBlob(async (blob) => {
          resolve(blob);
          // for (const [key, value] of data) {
          //   console.log("before", { [key]: value });
          // }
          // data.set(name, blob, "screenshot.png");
          // for (const [key, value] of data) {
          //   console.log("after", { [key]: value });
          // }
        });
      });
    });

    vid.srcObject = stream;
    vid.play();

    return promise;
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const data = new FormData(form);

    const includeScreenshot = form["include-screenshot"]?.checked;
    if (includeScreenshot) {
      try {
        container.hidden = true;

        // alert('Capturing screenshot …')
        const screenshot = await captureScreenshot(data, "screenshot");
        data.set("screenshot", screenshot, "screenshot.png");
      } finally {
        container.hidden = false;
      }
    }

    for (const [key, value] of data) {
      console.log("final", { [key]: value });
    }

    // alert('Done');

    const sendFeedback = () => {
      fetch(config.path, {
        method: "post",
        body: data,
      })
        .then((response) => alert(JSON.stringify({ response })))
        .catch((error) => alert(JSON.stringify({ error })));
    };

    // startCapture(displayMediaOptions)
    //   .then(capture => {
    //     if (!capture) {
    //       alert('Cannot capture image')
    //     } else {
    //       config.capture = capture
    //       sendFeedback()
    //     }
    //   });
    sendFeedback();
  });
});
