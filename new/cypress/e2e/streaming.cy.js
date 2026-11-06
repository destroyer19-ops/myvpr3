describe('MyVPR Dual-Streaming Platform Tests', () => {
  it('Loads the public streaming page correctly', () => {
    // We mock the API call since we don't know the state of the backend in this test runner
    cy.intercept('GET', '/api/streaming/watch/*', {
      statusCode: 200,
      body: {
        success: true,
        data: {
          playback_url: 'https://fcc3ddae59ed.us-west-2.playback.live-video.net/api/video/v1/us-west-2.893648527354.channel.DmumNckWFTqz.m3u8',
          chat_room_id: 'test-room-123'
        }
      }
    }).as('getStream');

    cy.visit('/stream/testuser');
    cy.wait('@getStream');
    
    // Verify the video player container is rendered
    cy.get('video').should('exist');
    cy.contains("testuser's Broadcast").should('be.visible');
    cy.contains('LIVE').should('be.visible');
  });

  it('Loads the Real-Time Comparison viewer correctly', () => {
    // Intercept the provision endpoint
    cy.intercept('POST', '/api/realtime/provision', {
      statusCode: 200,
      body: {
        success: true,
        stage_arn: 'arn:aws:ivs:us-east-1:123456789012:stage/dummy',
        token: 'dummy-viewer-token'
      }
    }).as('provisionRealTime');

    cy.visit('/watch/realtime-test/testuser');
    cy.wait('@provisionRealTime');

    // Verify Real-Time UI renders
    cy.contains("testuser's Broadcast").should('be.visible');
    cy.contains('REAL-TIME LIVE').should('be.visible');
  });
  
  it('Admin Comparison Dashboard requires login', () => {
    // Accessing an admin route while logged out should redirect to login
    cy.visit('/admin/studio/compare');
    cy.url().should('include', '/login');
  });
});
