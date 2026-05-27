import { Composition, getInputProps, staticFile } from 'remotion';
import { getVideoMetadata } from '@remotion/media-utils';
import { z } from 'zod';
import { YouTubeJumpIntro } from './YouTubeJumpIntro';
import { VideoWithYouTubeJump } from './VideoWithYouTubeJump';

const fps = 30;
const width = 1920;
const height = 1080;
const introFrames = 72;

const videoPropsSchema = z.object({
  sourceVideo: z.string().default('input.mp4'),
  hasSourceVideo: z.boolean().default(false),
});

type VideoProps = z.infer<typeof videoPropsSchema>;

const getSourceVideo = (props: Partial<VideoProps>) =>
  props.sourceVideo || 'input.mp4';

export const RemotionRoot = () => {
  return (
    <>
      <Composition
        id="YouTubeJumpIntro"
        component={YouTubeJumpIntro}
        durationInFrames={introFrames}
        fps={fps}
        width={width}
        height={height}
      />
      <Composition
        id="VideoWithYouTubeJump"
        component={VideoWithYouTubeJump}
        durationInFrames={introFrames + fps * 8}
        fps={fps}
        width={width}
        height={height}
        schema={videoPropsSchema}
        defaultProps={{
          sourceVideo: 'input.mp4',
          hasSourceVideo: false,
        }}
        calculateMetadata={async ({ props }) => {
          const sourceVideo = getSourceVideo({
            ...getInputProps<Partial<VideoProps>>(),
            ...props,
          });
          try {
            const metadata = await getVideoMetadata(staticFile(sourceVideo));
            const videoFrames = Math.max(
              1,
              Math.ceil(metadata.durationInSeconds * fps),
            );

            return {
              durationInFrames: videoFrames,
              props: {
                sourceVideo,
                hasSourceVideo: true,
              },
            };
          } catch {
            return {
              durationInFrames: introFrames + fps * 8,
              props: {
                sourceVideo,
                hasSourceVideo: false,
              },
            };
          }
        }}
      />
    </>
  );
};
