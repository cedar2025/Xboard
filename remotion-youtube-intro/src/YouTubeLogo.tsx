type YouTubeLogoProps = {
  scale?: number;
};

export const YouTubeLogo = ({ scale = 1 }: YouTubeLogoProps) => {
  const width = 420 * scale;
  const height = 300 * scale;
  const radius = 64 * scale;
  const triangleWidth = 98 * scale;
  const triangleHeight = 116 * scale;

  return (
    <div
      style={{
        width,
        height,
        borderRadius: radius,
        background: '#ff0033',
        boxShadow: '0 34px 90px rgba(255, 0, 51, 0.34)',
        display: 'grid',
        placeItems: 'center',
      }}
    >
      <div
        style={{
          width: 0,
          height: 0,
          borderTop: `${triangleHeight / 2}px solid transparent`,
          borderBottom: `${triangleHeight / 2}px solid transparent`,
          borderLeft: `${triangleWidth}px solid white`,
          transform: `translateX(${14 * scale}px)`,
        }}
      />
    </div>
  );
};
