# YouTube Jump Intro

Small Remotion project that adds a jumping YouTube-style logo at the beginning
of a video.

## Use

```bash
npm install
npm run start
```

Put your source video at:

```text
public/input.mp4
```

Render the video with the jumping intro overlay:

```bash
npm run render:video
```

Render only the standalone logo intro:

```bash
npm run render:intro
```

To use a different source filename:

```bash
npx remotion render VideoWithYouTubeJump out/video-with-youtube-jump.mp4 --props='{"sourceVideo":"your-video.mp4"}'
```
