import {
  AbsoluteFill,
  Easing,
  interpolate,
  spring,
  useCurrentFrame,
  useVideoConfig,
} from 'remotion';
import { YouTubeLogo } from './YouTubeLogo';

export const YouTubeJumpIntro = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const entrance = spring({
    frame,
    fps,
    config: {
      damping: 9,
      mass: 0.55,
      stiffness: 155,
    },
  });
  const hop = Math.sin((frame / fps) * Math.PI * 4) *
    interpolate(frame, [0, 52], [30, 0], {
      extrapolateLeft: 'clamp',
      extrapolateRight: 'clamp',
    });
  const scale = interpolate(entrance, [0, 1], [0.25, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });
  const rotation = interpolate(frame, [0, 14, 26, 44], [-14, 6, -3, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: Easing.out(Easing.cubic),
  });
  const fadeOut = interpolate(frame, [54, 71], [1, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

  return (
    <AbsoluteFill
      style={{
        background:
          'radial-gradient(circle at 50% 42%, #ffffff 0%, #f5f5f5 34%, #e9e9e9 100%)',
        alignItems: 'center',
        justifyContent: 'center',
        opacity: fadeOut,
      }}
    >
      <div
        style={{
          transform: `translateY(${hop}px) scale(${scale}) rotate(${rotation}deg)`,
          transformOrigin: 'center',
        }}
      >
        <YouTubeLogo />
      </div>
    </AbsoluteFill>
  );
};
