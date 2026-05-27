import {
  AbsoluteFill,
  OffthreadVideo,
  Sequence,
  staticFile,
  useCurrentFrame,
} from 'remotion';
import { YouTubeJumpIntro } from './YouTubeJumpIntro';

type VideoWithYouTubeJumpProps = {
  sourceVideo: string;
  hasSourceVideo: boolean;
};

export const VideoWithYouTubeJump = ({
  hasSourceVideo,
  sourceVideo,
}: VideoWithYouTubeJumpProps) => {
  const frame = useCurrentFrame();

  return (
    <AbsoluteFill style={{ background: '#111111' }}>
      {hasSourceVideo ? (
        <OffthreadVideo src={staticFile(sourceVideo)} />
      ) : (
        <AbsoluteFill
          style={{
            alignItems: 'center',
            color: '#f5f5f5',
            fontFamily:
              'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif',
            fontSize: 54,
            justifyContent: 'center',
          }}
        >
          Add your video at public/{sourceVideo}
        </AbsoluteFill>
      )}
      <Sequence from={0} durationInFrames={72}>
        <YouTubeJumpIntro />
      </Sequence>
      <div
        style={{
          position: 'absolute',
          inset: 0,
          background:
            frame < 72
              ? `rgba(0, 0, 0, ${Math.max(0, 0.34 - frame / 230)})`
              : 'transparent',
          pointerEvents: 'none',
        }}
      />
    </AbsoluteFill>
  );
};
